<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EnrollmentAssignment;
use App\Models\Room;
use App\Models\SalaryStructure;
use App\Models\Service;
use App\Models\StaffLeave;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff and Branches menu pages (Sprint 16): leave, staff assignments, salary structures, advances,
 * staff and services by branch, and rooms.
 */
class StaffAdminController extends Controller
{
    /** Leave / Absence: staff leave plus therapist leave entered on the Therapists page. */
    public function leaves(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::ACCOUNTS_PAYROLL_APPROVE, Permission::THERAPISTS_VIEW]);
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today()->addMonths(2)->endOfMonth();
        $branches = $request->user()->accessibleBranchIds();

        $staff = StaffLeave::with(['employee:id,name,designation,department,branch_id', 'creator:id,name'])
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->whereHas('employee', fn ($e) => $e->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches)))
            ->get()->map(fn (StaffLeave $l) => [
                'id' => $l->id, 'source' => 'staff', 'name' => $l->employee->name, 'role' => $l->employee->designation ?? $l->employee->department,
                'type' => $l->type, 'type_label' => StaffLeave::TYPES[$l->type] ?? $l->type, 'from' => $l->start_date->toDateString(), 'to' => $l->end_date->toDateString(),
                'days' => $l->days, 'reason' => $l->reason, 'by' => $l->creator?->name,
            ]);
        $therapist = TherapistLeave::with(['therapist:id,name,designation,primary_branch_id'])
            ->whereNotIn('id', StaffLeave::whereNotNull('therapist_leave_id')->select('therapist_leave_id'))
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->whereHas('therapist', fn ($t) => $t->when($branches !== null, fn ($q) => $q->whereIn('primary_branch_id', $branches)))
            ->get()->map(fn (TherapistLeave $l) => [
                'id' => $l->id, 'source' => 'therapist', 'name' => $l->therapist->name, 'role' => $l->therapist->designation ?? 'Therapist',
                'type' => 'other', 'type_label' => 'Therapist leave', 'from' => $l->start_date->toDateString(), 'to' => $l->end_date->toDateString(),
                'days' => $this->workingDays($l->start_date, $l->end_date), 'reason' => $l->reason, 'by' => null,
            ]);

        return response()->json(['data' => $staff->concat($therapist)->sortBy('from')->values(), 'types' => StaffLeave::TYPES]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::THERAPISTS_MANAGE]);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', Rule::in(array_keys(StaffLeave::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = Employee::with('therapist')->findOrFail($data['employee_id']);
        abort_unless($request->user()->accessibleBranchIds() === null || in_array($employee->branch_id, $request->user()->accessibleBranchIds(), true), 403);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $overlap = StaffLeave::where('employee_id', $employee->id)->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'This person already has leave on some of these days.']);
        }

        [$leave, $affected] = DB::transaction(function () use ($data, $employee, $start, $end, $request) {
            $therapistLeave = $employee->therapist
                ? $employee->therapist->leaves()->create(['start_date' => $start, 'end_date' => $end, 'reason' => StaffLeave::TYPES[$data['type']].' leave'.($data['reason'] ? ": {$data['reason']}" : ''), 'created_by' => $request->user()->id])
                : null;
            $leave = StaffLeave::create([...$data, 'days' => $this->workingDays($start, $end), 'therapist_leave_id' => $therapistLeave?->id, 'created_by' => $request->user()->id]);
            $affected = $employee->therapist
                ? Appointment::where('therapist_id', $employee->therapist->id)->whereBetween('date', [$start, $end])->whereIn('status', ['pending', 'confirmed'])->count()
                : 0;

            return [$leave, $affected];
        });

        return response()->json(['data' => ['id' => $leave->id, 'days' => $leave->days, 'appointments_to_reschedule' => $affected]], 201);
    }

    public function destroyLeave(Request $request, StaffLeave $leave): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::THERAPISTS_MANAGE]);
        DB::transaction(function () use ($leave) {
            TherapistLeave::whereKey($leave->therapist_leave_id)->delete();
            $leave->delete();
        });

        return response()->json(['message' => 'Leave cancelled.']);
    }

    /** Staff Assignments: who looks after which children right now (current class / trainer / therapist). */
    public function assignments(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_VIEW);
        $rows = EnrollmentAssignment::with(['enrollment.patient:id,name,patient_code', 'enrollment.therapyEnrollment.service:id,name', 'trainer:id,name', 'trainingGroup:id,name', 'therapist:id,name'])
            ->whereNull('to_date')
            ->whereHas('enrollment', fn ($e) => $e->visibleTo($request->user())->whereIn('status', EnrollmentStatus::open()))
            ->get();

        $byTherapist = $rows->whereNotNull('therapist_id')->groupBy('therapist_id')->map(fn ($list) => [
            'kind' => 'therapist', 'id' => $list->first()->therapist_id, 'name' => $list->first()->therapist?->name,
            'children' => $list->map(fn ($a) => [...$a->enrollment->patient->only(['id', 'name', 'patient_code']), 'detail' => $a->enrollment->therapyEnrollment?->service?->name])->sortBy('name')->values(),
        ]);
        $byTrainer = $rows->whereNotNull('trainer_id')->groupBy('trainer_id')->map(fn ($list) => [
            'kind' => 'trainer', 'id' => $list->first()->trainer_id, 'name' => $list->first()->trainer?->name,
            'children' => $list->map(fn ($a) => [...$a->enrollment->patient->only(['id', 'name', 'patient_code']), 'detail' => $a->trainingGroup?->name])->sortBy('name')->values(),
        ]);

        return response()->json(['data' => $byTherapist->concat($byTrainer)->sortByDesc(fn ($s) => count($s['children']))->values()]);
    }

    /** Salary Structures: the structure in force today for each employee, with how many changes so far. */
    public function structures(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::ACCOUNTS_PAYROLL_APPROVE]);
        $employees = $this->employees($request->user())->where('status', '!=', 'left')->with('salaryStructures')->orderBy('department')->orderBy('name')->get();

        return response()->json(['data' => $employees->map(function (Employee $e) {
            $current = $e->salaryStructures->first(fn (SalaryStructure $s) => $s->effective_from->lte(today()));

            return [
                'employee' => $e->only(['id', 'name', 'employee_code', 'designation', 'department', 'pay_type']),
                'current' => $current ? [
                    'effective_from' => $current->effective_from->toDateString(), 'components' => $current->components(),
                    'total' => $current->monthlyTotal(), 'included_sessions' => $current->included_sessions, 'revenue_share_percent' => $current->revenue_share_percent,
                ] : null,
                'upcoming' => $e->salaryStructures->first(fn (SalaryStructure $s) => $s->effective_from->gt(today()))?->effective_from?->toDateString(),
                'changes' => $e->salaryStructures->count(),
            ];
        })]);
    }

    /** Employee Advances: every advance given, with what is still to be deducted from salary. */
    public function advances(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::ACCOUNTS_PAYROLL_APPROVE]);
        $advances = EmployeeAdvance::with('employee:id,name,employee_code,designation')
            ->whereIn('employee_id', $this->employees($request->user())->select('id'))
            ->when($request->input('status') === 'open', fn ($a) => $a->where('balance', '>', 0))
            ->latest('date')->latest('id')->get();

        return response()->json(['data' => $advances->map(fn (EmployeeAdvance $a) => [
            'id' => $a->id, 'date' => $a->date->toDateString(), 'employee' => $a->employee->only(['id', 'name', 'employee_code', 'designation']),
            'amount' => (float) $a->amount, 'installment' => (float) $a->installment, 'balance' => (float) $a->balance, 'status' => $a->status, 'reason' => $a->reason,
        ]), 'outstanding' => round((float) $advances->sum('balance'), 2)]);
    }

    /** Staff by Branch: employees by department, therapists, trainers and login accounts per branch. */
    public function staffByBranch(Request $request): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_VIEW);
        $branches = $this->branches($request->user());

        return response()->json(['data' => $branches->map(fn (Branch $b) => [
            'branch' => $b->only(['id', 'name', 'code']),
            'employees' => Employee::where('branch_id', $b->id)->where('status', '!=', 'left')->orderBy('name')->get(['id', 'name', 'designation', 'department'])
                ->groupBy('department')->map(fn ($list) => $list->map->only(['id', 'name', 'designation'])->values()),
            'therapists' => Therapist::where('primary_branch_id', $b->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'designation']),
            'trainers' => Trainer::where('branch_id', $b->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::whereHas('branches', fn ($q) => $q->where('branches.id', $b->id))->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'status'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'status' => $u->status, 'roles' => $u->roles->pluck('name')]),
        ])]);
    }

    /**
     * Services by Branch: which services each branch can actually give — a therapist offering it works there
     * (weekly hours) or, for training, a class runs there.
     */
    public function servicesByBranch(Request $request): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_VIEW);
        $branches = $this->branches($request->user());
        $services = Service::where('is_active', true)->orderBy('category')->orderBy('sort_order')->get(['id', 'name', 'category']);
        $therapists = Therapist::with(['services:id', 'schedules:id,therapist_id,branch_id'])->where('status', 'active')->get(['id', 'name', 'primary_branch_id']);
        $classes = TrainingGroup::where('status', 'active')->get(['id', 'branch_id']);

        return response()->json(['data' => [
            'services' => $services->map(fn ($s) => [...$s->only(['id', 'name']), 'category' => $s->category->value]),
            'branches' => $branches->map(function (Branch $b) use ($services, $therapists, $classes) {
                $here = $therapists->filter(fn ($t) => $t->schedules->contains('branch_id', $b->id) || ($t->schedules->isEmpty() && $t->primary_branch_id === $b->id));

                return [
                    'branch' => $b->only(['id', 'name', 'code']),
                    'cells' => $services->mapWithKeys(fn ($s) => [$s->id => $s->category->value === 'training'
                        ? ['count' => $classes->where('branch_id', $b->id)->count(), 'unit' => 'classes']
                        : ['count' => $here->filter(fn ($t) => $t->services->contains('id', $s->id))->count(), 'unit' => 'therapists']]),
                ];
            }),
        ]]);
    }

    public function rooms(Request $request): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_VIEW);
        $ids = $this->branches($request->user())->pluck('id');

        return response()->json(['data' => Room::with('branch:id,name')->whereIn('branch_id', $ids)->orderBy('branch_id')->orderBy('name')->get()
            ->map(fn (Room $r) => [...$r->only(['id', 'branch_id', 'name', 'type', 'capacity', 'is_active']), 'branch' => $r->branch->name,
                'classes' => TrainingGroup::where('room_id', $r->id)->where('status', 'active')->pluck('name')])]);
    }

    public function saveRoom(Request $request, ?Room $room = null): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_MANAGE);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->where('branch_id', $request->input('branch_id'))->ignore($room)],
            'type' => ['required', Rule::in(['class', 'therapy', 'assessment', 'other'])],
            'capacity' => ['nullable', 'integer', 'between:1,200'],
            'is_active' => ['boolean'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);
        $room ? $room->update($data) : $room = Room::create($data);

        return response()->json(['data' => $room], $room->wasRecentlyCreated ? 201 : 200);
    }

    private function employees(User $user)
    {
        $branches = $user->accessibleBranchIds();

        return Employee::query()->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches));
    }

    private function branches(User $user)
    {
        $ids = $user->accessibleBranchIds();

        return Branch::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Calendar days minus Fridays (the weekly holiday). */
    private function workingDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days += $d->isFriday() ? 0 : 1;
        }

        return $days;
    }

    private function authorizeAny(User $user, array $permissions): void
    {
        abort_unless(collect($permissions)->contains(fn ($p) => $user->can($p)), 403);
    }
}
