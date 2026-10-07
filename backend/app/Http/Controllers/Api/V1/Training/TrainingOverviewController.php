<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingRecord;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Trainer "Today" screen, the students list, holidays and activity lookups. */
class TrainingOverviewController extends Controller
{
    /** GET /trainer/today — Plan §১০: today's classes, present/absent, pending notes. */
    public function today(Request $request): JsonResponse
    {
        $trainer = $request->user()->trainer;
        abort_unless($trainer, 403, 'This account is not linked to a trainer profile.');
        $date = today();

        $classes = TrainingGroup::with('schedules')->where('status', 'active')->taughtBy($trainer->id)->get();

        $today = $classes->filter(fn (TrainingGroup $g) => $g->scheduleFor($date))->map(function (TrainingGroup $g) use ($date) {
            $students = $g->rosterOn($date)->count();
            $marks = TrainingAttendance::where('training_group_id', $g->id)->whereDate('date', $date)->get();
            $attended = $marks->filter(fn ($a) => $a->status->attended())->count();
            $recorded = TrainingRecord::whereHas('session', fn ($s) => $s->where('training_group_id', $g->id)->whereDate('date', $date))->count();
            $schedule = $g->scheduleFor($date);

            return [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
                'is_holiday' => $g->isHoliday($date),
                'students' => $students,
                'marked' => $marks->count(),
                'present' => $attended,
                'absent' => $marks->filter(fn ($a) => $a->status === AttendanceStatus::Absent)->count(),
                'pending_records' => max(0, $attended - $recorded),
            ];
        })->sortBy('start_time')->values();

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'trainer' => $trainer->only(['id', 'name']),
            'classes' => $today,
            'other_classes' => $classes->reject(fn ($g) => $g->scheduleFor($date))->map->only(['id', 'name', 'code'])->values(),
            'totals' => [
                'students' => $today->sum('students'),
                'present' => $today->sum('present'),
                'absent' => $today->sum('absent'),
                'pending_records' => $today->sum('pending_records'),
            ],
        ]]);
    }

    /** GET /students — regular students (open training enrollments) with this month's attendance rate. */
    public function students(Request $request, AttendanceService $attendance): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_VIEW);

        $enrollments = Enrollment::visibleTo($request->user())
            ->where('type', EnrollmentType::Training)
            ->whereIn('status', EnrollmentStatus::open())
            ->with(['patient:id,name,patient_code,photo_path,date_of_birth', 'trainingEnrollment.trainingGroup:id,name,code', 'trainingEnrollment.trainer:id,name'])
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('trainingEnrollment', fn ($t) => $t->where('training_group_id', $request->integer('class_id'))))
            ->get();

        $month = TrainingAttendance::whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get()->groupBy('enrollment_id');

        return response()->json(['data' => $enrollments->map(fn (Enrollment $e) => [
            'enrollment_id' => $e->id,
            'status' => $e->status,
            'start_date' => $e->start_date->toDateString(),
            'patient' => ['id' => $e->patient->id, 'name' => $e->patient->name, 'patient_code' => $e->patient->patient_code, 'has_photo' => $e->patient->photo_path !== null, 'age' => $e->patient->age],
            'class' => $e->trainingEnrollment->trainingGroup->only(['id', 'name', 'code']),
            'trainer' => $e->trainingEnrollment->trainer->only(['id', 'name']),
            'month_attendance' => $attendance->summary(($month[$e->id] ?? collect())->pluck('status')),
        ])->sortBy('patient.name')->values()]);
    }

    public function activityTypes(Request $request): JsonResponse
    {
        $for = $request->input('for', 'training');

        return response()->json(['data' => ActivityType::where('is_active', true)
            ->whereIn('applies_to', [$for, 'both'])->orderBy('sort_order')->get(['id', 'name', 'name_bn'])]);
    }

    public function holidays(Request $request): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_VIEW);
        $year = $request->integer('year', now()->year);
        $branchIds = $request->user()->accessibleBranchIds();

        return response()->json(['data' => Holiday::with('branch:id,name')
            ->whereYear('date', $year)
            ->when($branchIds !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $branchIds)))
            ->orderBy('date')->get()
            ->map(fn (Holiday $h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'title' => $h->title, 'type' => $h->type, 'branch' => $h->branch?->name])]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_MANAGE);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['public', 'center'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        // "All branches" holidays are a Super Admin decision.
        abort_if(empty($data['branch_id']) && ! $request->user()->hasRole(Role::SuperAdmin->value), 403);
        abort_if(! empty($data['branch_id']) && ! $request->user()->canAccessBranch($data['branch_id']), 403);

        $holiday = Holiday::updateOrCreate(['branch_id' => $data['branch_id'] ?? null, 'date' => $data['date']], $data);

        return response()->json(['data' => ['id' => $holiday->id]], 201);
    }

    public function destroyHoliday(Request $request, Holiday $holiday): JsonResponse
    {
        Gate::authorize(Permission::BRANCHES_MANAGE);
        abort_if($holiday->branch_id === null && ! $request->user()->hasRole(Role::SuperAdmin->value), 403);
        abort_if($holiday->branch_id && ! $request->user()->canAccessBranch($holiday->branch_id), 403);

        $holiday->delete();

        return response()->json(null, 204);
    }
}
