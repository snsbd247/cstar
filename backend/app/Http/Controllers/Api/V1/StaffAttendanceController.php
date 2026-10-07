<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff → Attendance (Sprint 20). Staff check themselves in and out from the dashboard; HR sees the monthly
 * sheet (leave, holidays and the weekly day off filled in) and corrects or enters days by hand.
 */
class StaffAttendanceController extends Controller
{
    public function __construct(private SystemSettings $settings) {}

    /** GET /me/attendance — today's state for the signed-in staff member (null when they have no employee record). */
    public function mine(Request $request): JsonResponse
    {
        $employee = $this->employeeOf($request->user());
        if (! $employee || ! $this->settings->flag('staff_attendance', 'self_check_in')) {
            return response()->json(['data' => null]);
        }
        $today = StaffAttendance::where('employee_id', $employee->id)->whereDate('date', today())->first();
        $month = StaffAttendance::where('employee_id', $employee->id)->whereBetween('date', [today()->startOfMonth(), today()])->get();

        return response()->json(['data' => [
            'employee' => $employee->only(['id', 'name', 'designation']),
            'today' => $today ? $this->row($today) : null,
            'office_start' => $this->settings->get('staff_attendance', 'office_start'),
            'month' => ['present' => $month->whereIn('status', ['present', 'late'])->count(), 'late' => $month->where('status', 'late')->count()],
        ]]);
    }

    /** POST /me/attendance/{action} — check-in or check-out, once each per day. */
    public function punch(Request $request, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['check-in', 'check-out'], true), 404);
        abort_unless($this->settings->flag('staff_attendance', 'self_check_in'), 403, 'Self check-in is turned off.');
        $employee = $this->employeeOf($request->user()) ?? abort(403, 'Your login is not linked to an employee record.');
        $record = StaffAttendance::where('employee_id', $employee->id)->whereDate('date', today())->first();
        $now = now()->format('H:i:s');

        if ($action === 'check-in') {
            if ($record?->check_in) {
                throw ValidationException::withMessages(['action' => 'Already checked in at '.substr($record->check_in, 0, 5).'.']);
            }
            $lateAt = today()->setTimeFromTimeString($this->settings->get('staff_attendance', 'office_start'))
                ->addMinutes($this->settings->int('staff_attendance', 'late_after_minutes'));
            $record = StaffAttendance::updateOrCreate(['employee_id' => $employee->id, 'date' => today()->toDateString()], [
                'check_in' => $now, 'status' => now()->gt($lateAt) ? 'late' : 'present', 'source' => 'self', 'recorded_by' => $request->user()->id,
            ]);
        } else {
            if (! $record?->check_in) {
                throw ValidationException::withMessages(['action' => 'Check in first.']);
            }
            if ($record->check_out) {
                throw ValidationException::withMessages(['action' => 'Already checked out at '.substr($record->check_out, 0, 5).'.']);
            }
            $record->update(['check_out' => $now]);
        }

        return response()->json(['data' => $this->row($record->fresh())]);
    }

    /** GET /hr/attendance?month=YYYY-MM — employees × days, with leave, holidays and the weekly day off. */
    public function sheet(Request $request): JsonResponse
    {
        $this->authorizeHr($request->user());
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth() : today()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $branches = $request->user()->accessibleBranchIds();

        $employees = Employee::where('status', '!=', 'left')
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->orderBy('name')->get(['id', 'name', 'designation', 'department', 'branch_id', 'joining_date']);
        $records = StaffAttendance::whereIn('employee_id', $employees->pluck('id'))->whereBetween('date', [$month, $end])->get()
            ->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->date->toDateString()));
        $leaves = StaffLeave::whereIn('employee_id', $employees->pluck('id'))->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $month)->get()->groupBy('employee_id');
        $holidays = Holiday::whereBetween('date', [$month, $end])->get(['branch_id', 'date', 'title']);
        $weeklyOff = $this->settings->get('staff_attendance', 'weekly_off');

        $days = [];
        for ($d = $month->copy(); $d->lte($end); $d->addDay()) {
            $days[] = ['date' => $d->toDateString(), 'weekday' => $d->dayOfWeek, 'off' => $weeklyOff !== 'none' && $d->dayOfWeek === (int) $weeklyOff];
        }

        $rows = $employees->map(function (Employee $e) use ($days, $records, $leaves, $holidays) {
            $mine = $records->get($e->id, collect());
            $cells = [];
            $summary = ['present' => 0, 'late' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0, 'unmarked' => 0];
            foreach ($days as $day) {
                $date = $day['date'];
                $record = $mine->get($date);
                $leave = $leaves->get($e->id)?->first(fn ($l) => $l->start_date->toDateString() <= $date && $l->end_date->toDateString() >= $date);
                $holiday = $holidays->first(fn ($h) => $h->date->toDateString() === $date && ($h->branch_id === null || $h->branch_id === $e->branch_id));
                $code = match (true) {
                    $record !== null => $record->status,
                    $leave !== null => 'leave',
                    $holiday !== null => 'holiday',
                    $day['off'] => 'off',
                    $date > today()->toDateString() || ($e->joining_date && $date < $e->joining_date->toDateString()) => null,
                    default => 'unmarked',
                };
                if ($code !== null && isset($summary[$code])) {
                    $summary[$code]++;
                }
                $cells[$date] = $code === null ? null : [
                    'code' => $code, 'in' => $record?->check_in ? substr($record->check_in, 0, 5) : null, 'out' => $record?->check_out ? substr($record->check_out, 0, 5) : null,
                    'note' => $record?->note ?? $leave?->reason ?? $holiday?->title,
                ];
            }

            return ['employee' => $e->only(['id', 'name', 'designation', 'department']), 'cells' => $cells, 'summary' => $summary];
        });

        return response()->json(['data' => ['month' => $month->format('Y-m'), 'days' => $days, 'rows' => $rows->values()]]);
    }

    /** PUT /hr/attendance — HR enters or corrects one day (status "clear" removes the entry). */
    public function save(Request $request): JsonResponse
    {
        $this->authorizeHr($request->user(), manage: true);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in([...StaffAttendance::STATUSES, 'clear'])],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless($employee->branch_id === null || $request->user()->canAccessBranch($employee->branch_id), 403);

        if ($data['status'] === 'clear') {
            StaffAttendance::where('employee_id', $employee->id)->whereDate('date', $data['date'])->first()?->delete();

            return response()->json(['data' => null]);
        }
        $record = StaffAttendance::updateOrCreate(['employee_id' => $employee->id, 'date' => Carbon::parse($data['date'])->toDateString()], [
            'status' => $data['status'], 'check_in' => $data['check_in'] ?? null, 'check_out' => $data['check_out'] ?? null,
            'note' => $data['note'] ?? null, 'source' => 'hr', 'recorded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->row($record)]);
    }

    private function employeeOf(User $user): ?Employee
    {
        return Employee::where('user_id', $user->id)->where('status', '!=', 'left')->first();
    }

    private function row(StaffAttendance $a): array
    {
        return [
            'date' => $a->date->toDateString(), 'status' => $a->status, 'source' => $a->source, 'note' => $a->note,
            'check_in' => $a->check_in ? substr($a->check_in, 0, 5) : null, 'check_out' => $a->check_out ? substr($a->check_out, 0, 5) : null,
        ];
    }

    private function authorizeHr(User $user, bool $manage = false): void
    {
        abort_unless($user->can(Permission::ACCOUNTS_PAYROLL_MANAGE) || (! $manage && $user->can(Permission::ACCOUNTS_PAYROLL_APPROVE)), 403);
    }
}
