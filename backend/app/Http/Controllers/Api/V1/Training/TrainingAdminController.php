<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\IndividualPlan;
use App\Models\Patient;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingRecord;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Training menu pages for the admin panel (Sprint 16): dashboard, weekly schedule, attendance by day,
 * class sessions, activities and individual plans (ITP). Attendance and records are still written in
 * the trainer app or on the class page.
 */
class TrainingAdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::CLASSES_VIEW, Permission::TRAINING_RECORDS_VIEW]);
        $today = today();
        $classes = $this->classes($request->user())->with(['schedules', 'leadTrainer:id,name'])->where('status', 'active')->get();
        $students = Enrollment::where('type', EnrollmentType::Training)->where('status', EnrollmentStatus::Active)
            ->whereIn('branch_id', $classes->pluck('branch_id')->unique())->count();
        $monthMarks = TrainingAttendance::whereIn('training_group_id', $classes->pluck('id'))
            ->whereBetween('date', [$today->copy()->startOfMonth(), $today])->get(['status']);
        $attended = $monthMarks->filter(fn ($m) => $m->status->attended())->count();
        $counted = $monthMarks->reject(fn ($m) => in_array($m->status, [AttendanceStatus::Holiday, AttendanceStatus::Leave], true))->count();

        return response()->json(['data' => [
            'kpis' => [
                'active_students' => $students,
                'active_classes' => $classes->count(),
                'attendance_rate' => $counted ? round($attended / $counted * 100) : null,
                'records_this_month' => TrainingRecord::whereHas('session', fn ($s) => $s->whereIn('training_group_id', $classes->pluck('id'))
                    ->whereBetween('date', [$today->copy()->startOfMonth(), $today]))->count(),
            ],
            'today' => $this->daySummary($classes, $today),
            'near_capacity' => $classes->map(fn (TrainingGroup $g) => ['id' => $g->id, 'name' => $g->name, 'seats' => $g->occupiedSeats(), 'max' => $g->max_students])
                ->filter(fn ($c) => $c['max'] && $c['seats'] >= $c['max'] - 1)->values(),
            'holidays' => Holiday::whereBetween('date', [$today, $today->copy()->addDays(30)])
                ->when($request->user()->accessibleBranchIds() !== null, fn ($h) => $h->where(fn ($w) => $w->whereNull('branch_id')->orWhereIn('branch_id', $request->user()->accessibleBranchIds())))
                ->orderBy('date')->get(['date', 'title'])->map(fn ($h) => ['date' => $h->date->toDateString(), 'name' => $h->title]),
        ]]);
    }

    /** Training Schedules: every active class and its weekly days and times. */
    public function schedule(Request $request): JsonResponse
    {
        Gate::authorize(Permission::CLASSES_VIEW);
        $classes = $this->classes($request->user())->with(['schedules', 'leadTrainer:id,name', 'branch:id,name'])->where('status', 'active')->orderBy('name')->get();

        return response()->json(['data' => $classes->map(fn (TrainingGroup $g) => [
            'id' => $g->id, 'name' => $g->name, 'code' => $g->code, 'branch' => $g->branch->name, 'trainer' => $g->leadTrainer?->name,
            'seats' => $g->occupiedSeats(), 'max' => $g->max_students,
            'days' => $g->schedules->sortBy('weekday')->values()->map(fn ($s) => ['weekday' => $s->weekday, 'start' => substr($s->start_time, 0, 5), 'end' => substr($s->end_time, 0, 5)]),
        ])]);
    }

    /** Attendance: how far each class got with marking on one day. Marking itself happens on the class page. */
    public function attendance(Request $request): JsonResponse
    {
        Gate::authorize(Permission::TRAINING_ATTENDANCE_VIEW);
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : today();
        $classes = $this->classes($request->user())->with(['schedules', 'leadTrainer:id,name'])->where('status', 'active')->get();

        return response()->json(['data' => ['date' => $date->toDateString(), 'classes' => $this->daySummary($classes, $date)]]);
    }

    /** Training Sessions: each class meeting (one per class per day) with attendance and records written. */
    public function sessions(Request $request): JsonResponse
    {
        Gate::authorize(Permission::TRAINING_RECORDS_VIEW);
        $classIds = $this->classes($request->user())->select('id');

        $page = TrainingSession::with(['trainingGroup:id,name,code', 'trainer:id,name'])->withCount('records')
            ->whereIn('training_group_id', $classIds)
            ->when($request->filled('class_id'), fn ($q) => $q->where('training_group_id', $request->integer('class_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->latest('date')->latest('id')->paginate(30);
        $marks = TrainingAttendance::query()
            ->whereIn('training_group_id', collect($page->items())->pluck('training_group_id')->unique())
            ->whereIn('date', collect($page->items())->map(fn ($s) => $s->date->toDateString())->unique())
            ->get(['training_group_id', 'date', 'status'])
            ->groupBy(fn ($m) => $m->training_group_id.'|'.$m->date->toDateString());

        return response()->json([
            'data' => collect($page->items())->map(function (TrainingSession $s) use ($marks) {
                $day = $marks[$s->training_group_id.'|'.$s->date->toDateString()] ?? collect();

                return [
                    'id' => $s->id, 'date' => $s->date->toDateString(), 'class' => $s->trainingGroup->only(['id', 'name', 'code']),
                    'trainer' => $s->trainer?->name, 'theme' => $s->theme, 'notes' => $s->notes,
                    'present' => $day->filter(fn ($m) => $m->status->attended())->count(),
                    'absent' => $day->where('status', AttendanceStatus::Absent)->count(),
                    'records' => $s->records_count,
                ];
            }),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function activities(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::CLASSES_VIEW, Permission::THERAPISTS_VIEW]);

        return response()->json(['data' => ActivityType::orderBy('applies_to')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::CLASSES_MANAGE, Permission::THERAPISTS_MANAGE]);

        return response()->json(['data' => ActivityType::create($this->activityData($request))], 201);
    }

    public function updateActivity(Request $request, ActivityType $activityType): JsonResponse
    {
        $this->authorizeAny($request->user(), [Permission::CLASSES_MANAGE, Permission::THERAPISTS_MANAGE]);
        $activityType->update($this->activityData($request, $activityType));

        return response()->json(['data' => $activityType]);
    }

    /** ITP / Plans & Goals: individual plans of all children, training or therapy, with goal progress. */
    public function plans(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PLANS_VIEW);
        $request->validate(['type' => ['nullable', Rule::in(['training', 'therapy'])], 'status' => ['nullable', 'string', 'max:20']]);
        $q = trim((string) $request->input('q'));

        $page = IndividualPlan::with(['enrollment.patient:id,name,patient_code', 'enrollment.therapyEnrollment.service:id,name', 'enrollment.trainingEnrollment.trainingGroup:id,name', 'goals:id,individual_plan_id,status,progress_percent'])
            ->whereIn('patient_id', Patient::visibleTo($request->user())->select('id'))
            ->when($request->filled('type'), fn ($p) => $p->whereHas('enrollment', fn ($e) => $e->where('type', $request->input('type'))))
            ->when($request->filled('status'), fn ($p) => $p->where('status', $request->string('status')))
            ->when($request->input('review') === 'due', fn ($p) => $p->where('status', 'active')->whereDate('review_date', '<=', today()->addDays(7)))
            ->when($q !== '', fn ($p) => $p->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhereHas('enrollment.patient', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%"))))
            ->latest('start_date')->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (IndividualPlan $p) => [
                'id' => $p->id, 'title' => $p->title, 'status' => $p->status,
                'start_date' => $p->start_date?->toDateString(), 'review_date' => $p->review_date?->toDateString(),
                'review_due' => $p->status === 'active' && $p->review_date && $p->review_date->lte(today()->addDays(7)),
                'type' => $p->enrollment->type->value,
                'programme' => $p->enrollment->therapyEnrollment?->service?->name ?? ('Regular Training — '.$p->enrollment->trainingEnrollment?->trainingGroup?->name),
                'patient' => $p->enrollment->patient->only(['id', 'name', 'patient_code']),
                'goals' => $p->goals->count(),
                'achieved' => $p->goals->where('status', 'achieved')->count(),
                'progress' => $p->goals->count() ? (int) round($p->goals->avg('progress_percent')) : null,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** @param  \Illuminate\Support\Collection<int, TrainingGroup>  $classes */
    private function daySummary($classes, Carbon $date): array
    {
        $marks = TrainingAttendance::whereIn('training_group_id', $classes->pluck('id'))->whereDate('date', $date)->get(['training_group_id', 'status'])->groupBy('training_group_id');
        $records = TrainingRecord::whereHas('session', fn ($s) => $s->whereIn('training_group_id', $classes->pluck('id'))->whereDate('date', $date))
            ->with('session:id,training_group_id')->get(['id', 'training_session_id'])->groupBy(fn ($r) => $r->session->training_group_id);

        return $classes->filter(fn (TrainingGroup $g) => $g->scheduleFor($date))->map(function (TrainingGroup $g) use ($date, $marks, $records) {
            $day = $marks[$g->id] ?? collect();
            $schedule = $g->scheduleFor($date);
            $present = $day->filter(fn ($m) => $m->status->attended())->count();

            return [
                'id' => $g->id, 'name' => $g->name, 'code' => $g->code, 'trainer' => $g->leadTrainer?->name,
                'start' => substr($schedule->start_time, 0, 5), 'end' => substr($schedule->end_time, 0, 5),
                'is_holiday' => $g->isHoliday($date), 'students' => $g->rosterOn($date)->count(),
                'marked' => $day->count(), 'present' => $present,
                'absent' => $day->filter(fn ($m) => $m->status === AttendanceStatus::Absent)->count(),
                'records' => ($records[$g->id] ?? collect())->count(),
            ];
        })->sortBy('start')->values()->all();
    }

    private function classes(User $user): Builder
    {
        $branches = $user->accessibleBranchIds();

        return TrainingGroup::query()->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches));
    }

    private function activityData(Request $request, ?ActivityType $current = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('activity_types', 'name')->where('applies_to', $request->input('applies_to'))->ignore($current)],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'applies_to' => ['required', Rule::in(['training', 'therapy', 'both'])],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);
    }

    private function authorizeAny(User $user, array $permissions): void
    {
        abort_unless(collect($permissions)->contains(fn ($p) => $user->can($p)), 403);
    }
}
