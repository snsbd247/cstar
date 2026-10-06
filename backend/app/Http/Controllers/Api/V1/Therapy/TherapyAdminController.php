<?php

namespace App\Http\Controllers\Api\V1\Therapy;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentRecommendation;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\IndividualPlan;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Therapy and Assessments menu pages (Sprint 16): dashboard, therapist weekly schedule, home programmes,
 * progress reports, services, assessment types / templates and the recommendations waiting for enrollment.
 */
class TherapyAdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::THERAPY_SESSIONS_VIEW) || $request->user()->can(Permission::APPOINTMENTS_VIEW), 403);
        $user = $request->user();
        $today = today();
        $todays = $this->appointments($user)->with('therapist:id,name')->whereDate('date', $today)->get(['id', 'therapist_id', 'status']);
        $month = $this->appointments($user)->whereBetween('date', [$today->copy()->startOfMonth(), $today])
            ->whereIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])->pluck('status');
        $noShows = $month->filter(fn ($s) => $s === AppointmentStatus::NoShow)->count();

        return response()->json(['data' => [
            'kpis' => [
                'today' => $todays->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::Rescheduled])->count(),
                'active_patients' => Enrollment::visibleTo($user)->where('type', EnrollmentType::Therapy)->where('status', EnrollmentStatus::Active)->distinct()->count('patient_id'),
                'sessions_this_week' => TherapySession::whereHas('patient', fn ($p) => $p->visibleTo($user))->where('status', 'final')
                    ->whereBetween('date', [$today->copy()->subDays(6), $today])->count(),
                'notes_pending' => $this->appointments($user)->whereBetween('date', [$today->copy()->subDays(14), $today])
                    ->whereIn('status', [AppointmentStatus::CheckedIn->value, AppointmentStatus::Completed->value])->where('type', 'therapy')
                    ->where(fn ($q) => $q->whereDoesntHave('session')->orWhereHas('session', fn ($s) => $s->where('status', '!=', 'final')))->count(),
                'no_show_rate' => $month->count() ? round($noShows / $month->count() * 100) : null,
                'draft_assessments' => Assessment::whereHas('patient', fn ($p) => $p->visibleTo($user))->where('status', 'draft')->count(),
            ],
            'today_by_status' => $todays->groupBy(fn ($a) => $a->status->value)->map->count(),
            'therapists_today' => $todays->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::Rescheduled])->groupBy('therapist_id')
                ->map(fn ($list) => ['name' => $list->first()->therapist?->name, 'total' => $list->count(), 'done' => $list->where('status', AppointmentStatus::Completed)->count()])
                ->sortByDesc('total')->values(),
            'on_leave' => TherapistLeave::with('therapist:id,name')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->get()
                ->map(fn ($l) => ['name' => $l->therapist?->name, 'until' => $l->end_date->toDateString(), 'reason' => $l->reason]),
            'pending_recommendations' => $this->recommendationQuery($user)->whereNull('enrollment_id')->count(),
        ]]);
    }

    /** Therapist Schedule: weekly working hours, leave in the week and appointments per day. */
    public function schedule(Request $request): JsonResponse
    {
        Gate::authorize(Permission::THERAPISTS_VIEW);
        $start = Carbon::parse($request->input('week', today()->toDateString()))->startOfDay();
        $start->subDays(($start->dayOfWeek + 1) % 7); // weeks start on Saturday
        $end = $start->copy()->addDays(6);
        $branches = $request->user()->accessibleBranchIds();

        $therapists = Therapist::with(['schedules' => fn ($q) => $q->when($branches !== null, fn ($s) => $s->whereIn('branch_id', $branches))->orderBy('start_time'), 'schedules.branch:id,name',
            'leaves' => fn ($q) => $q->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)])
            ->where('status', 'active')->orderBy('name')->get();
        $counts = $this->appointments($request->user())->whereBetween('date', [$start, $end])
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::Rescheduled->value])
            ->selectRaw('therapist_id, date, COUNT(*) as n')->groupBy('therapist_id', 'date')->get()
            ->groupBy('therapist_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [Carbon::parse($r->date)->toDateString() => (int) $r->n]));

        return response()->json(['data' => [
            'week_start' => $start->toDateString(),
            'days' => collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)->toDateString()),
            'therapists' => $therapists->filter(fn ($t) => $branches === null || $t->schedules->isNotEmpty())->values()->map(fn (Therapist $t) => [
                'id' => $t->id, 'name' => $t->name, 'type' => $t->therapist_type,
                'hours' => $t->schedules->groupBy('weekday')->map(fn ($rows) => $rows->map(fn ($s) => substr($s->start_time, 0, 5).'–'.substr($s->end_time, 0, 5).($s->branch ? " ({$s->branch->name})" : ''))->values()),
                'leave' => $t->leaves->map(fn ($l) => ['from' => $l->start_date->toDateString(), 'to' => $l->end_date->toDateString(), 'reason' => $l->reason])->values(),
                'appointments' => $counts[$t->id] ?? (object) [],
            ]),
        ]]);
    }

    /** Home Programs: the home practice from finalized therapy sessions, newest first. */
    public function homePrograms(Request $request): JsonResponse
    {
        Gate::authorize(Permission::HOME_PROGRAMS_VIEW);
        $q = trim((string) $request->input('q'));

        $page = TherapySession::with(['patient:id,name,patient_code', 'therapist:id,name', 'service:id,name'])
            ->whereHas('patient', fn ($p) => $p->visibleTo($request->user()))
            ->where('status', 'final')->whereNotNull('home_practice')->where('home_practice', '!=', '')
            ->when($request->filled('therapist_id'), fn ($s) => $s->where('therapist_id', $request->integer('therapist_id')))
            ->when($q !== '', fn ($s) => $s->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%")))
            ->latest('date')->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (TherapySession $s) => [
                'id' => $s->id, 'date' => $s->date->toDateString(), 'patient' => $s->patient->only(['id', 'name', 'patient_code']),
                'therapist' => $s->therapist?->name, 'service' => $s->service?->name, 'home_practice' => $s->home_practice,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Progress Reports: children in a programme, with what the PDF report will contain. */
    public function progressReports(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PLANS_VIEW);
        $q = trim((string) $request->input('q'));

        $page = Patient::visibleTo($request->user())
            ->whereHas('enrollments', fn ($e) => $e->whereIn('status', EnrollmentStatus::open()))
            ->with(['enrollments' => fn ($e) => $e->whereIn('status', EnrollmentStatus::open())->with(['therapyEnrollment.service:id,name', 'trainingEnrollment.trainingGroup:id,name'])])
            ->when($q !== '', fn ($p) => $p->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%")))
            ->orderBy('name')->paginate(30);
        $ids = collect($page->items())->pluck('id');
        $lastAssessment = Assessment::whereIn('patient_id', $ids)->where('status', 'final')->selectRaw('patient_id, MAX(date) as d')->groupBy('patient_id')->pluck('d', 'patient_id');
        $plans = IndividualPlan::whereIn('patient_id', $ids)->where('status', 'active')->selectRaw('patient_id, COUNT(*) as n')->groupBy('patient_id')->pluck('n', 'patient_id');
        $sessions = TherapySession::whereIn('patient_id', $ids)->where('status', 'final')->where('date', '>=', today()->subDays(90))
            ->selectRaw('patient_id, COUNT(*) as n')->groupBy('patient_id')->pluck('n', 'patient_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (Patient $p) => [
                'patient' => $p->only(['id', 'name', 'patient_code']),
                'programmes' => $p->enrollments->map(fn ($e) => $e->therapyEnrollment?->service?->name ?? 'Regular Training — '.$e->trainingEnrollment?->trainingGroup?->name)->values(),
                'last_assessment' => isset($lastAssessment[$p->id]) ? Carbon::parse($lastAssessment[$p->id])->toDateString() : null,
                'active_plans' => (int) ($plans[$p->id] ?? 0),
                'sessions_90_days' => (int) ($sessions[$p->id] ?? 0),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::THERAPISTS_VIEW) || $request->user()->can(Permission::PACKAGES_VIEW), 403);

        return response()->json(['data' => Service::withCount(['therapists' => fn ($t) => $t->where('status', 'active')])
            ->orderBy('category')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Service $s) => [
                ...$s->only(['id', 'name', 'name_bn', 'slug', 'default_duration_min', 'default_price', 'is_bookable_online', 'show_on_website', 'is_active', 'sort_order']),
                'category' => $s->category->value, 'therapists' => $s->therapists_count,
            ])]);
    }

    public function storeService(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_MANAGE);
        $data = $this->serviceData($request);
        $slug = Str::slug($data['name']);
        $data['slug'] = Service::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;

        return response()->json(['data' => Service::create($data)], 201);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_MANAGE);
        $service->update($this->serviceData($request, $service));

        return response()->json(['data' => $service]);
    }

    /** Assessment Types and their report sections (templates). Section keys already used in an assessment stay. */
    public function assessmentTypes(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ASSESSMENTS_VIEW);
        $used = $this->usedSections();

        return response()->json(['data' => AssessmentType::withCount('assessments')->orderBy('sort_order')->get()->map(fn (AssessmentType $t) => [
            ...$t->only(['id', 'name', 'name_bn', 'is_active', 'sort_order']),
            'sections' => collect($t->sections)->map(fn ($s) => [...$s, 'used' => in_array($s['key'], $used[$t->id] ?? [], true)])->values(),
            'assessments' => $t->assessments_count,
        ])]);
    }

    public function saveAssessmentType(Request $request, ?AssessmentType $type = null): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('assessment_types', 'name')->ignore($type)],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*.key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'sections.*.label' => ['required', 'string', 'max:120'],
        ]);

        $sections = collect($data['sections'])->map(fn ($s) => ['key' => $s['key'] ?: Str::snake(Str::limit(Str::ascii($s['label']), 40, '')), 'label' => $s['label']]);
        if ($sections->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['sections' => 'Two sections have the same name.']);
        }
        if ($type) {
            $missing = array_diff($this->usedSections()[$type->id] ?? [], $sections->pluck('key')->all());
            if ($missing) {
                throw ValidationException::withMessages(['sections' => 'These sections already hold findings in saved assessments and cannot be removed: '.implode(', ', $missing).'. Rename them instead.']);
            }
        }

        $values = ['name' => $data['name'], 'name_bn' => $data['name_bn'] ?? null, 'is_active' => $data['is_active'] ?? true, 'sections' => $sections->values()->all()];
        $old = $type?->only(['name', 'sections', 'is_active']);
        $type ? $type->update($values) : $type = AssessmentType::create([...$values, 'sort_order' => (int) AssessmentType::max('sort_order') + 1]);
        AuditLogger::log($old ? 'updated' : 'created', $type, $old, $type->only(['name', 'sections', 'is_active']));

        return response()->json(['data' => $type], $old ? 200 : 201);
    }

    /** Recommendations from final assessments; "pending" = no enrollment made from it yet. */
    public function recommendations(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ASSESSMENTS_VIEW);
        $page = $this->recommendationQuery($request->user())
            ->with(['assessment:id,assessment_code,patient_id,therapist_id,date', 'assessment.patient:id,name,patient_code', 'assessment.therapist:id,name', 'service:id,name'])
            ->when($request->input('status', 'pending') === 'pending', fn ($r) => $r->whereNull('enrollment_id'), fn ($r) => $r->whereNotNull('enrollment_id'))
            ->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (AssessmentRecommendation $r) => [
                'id' => $r->id, 'programme' => $r->enrollment_type === 'training' ? 'Regular Training' : ($r->service?->name ?? 'Therapy'),
                'frequency' => $r->frequency, 'priority' => $r->priority, 'note' => $r->note, 'enrolled' => $r->enrollment_id !== null,
                'assessment' => ['id' => $r->assessment->id, 'code' => $r->assessment->assessment_code, 'date' => $r->assessment->date->toDateString(), 'therapist' => $r->assessment->therapist?->name],
                'patient' => $r->assessment->patient->only(['id', 'name', 'patient_code']),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    private function recommendationQuery(User $user): Builder
    {
        return AssessmentRecommendation::query()->whereHas('assessment', fn ($a) => $a->where('status', 'final')->whereHas('patient', fn ($p) => $p->visibleTo($user)));
    }

    private function appointments(User $user): Builder
    {
        $branches = $user->accessibleBranchIds();

        return Appointment::query()->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches));
    }

    /** @return array<int, list<string>> section keys with findings, per assessment type */
    private function usedSections(): array
    {
        return Assessment::whereNotNull('section_findings')->get(['assessment_type_id', 'section_findings'])
            ->groupBy('assessment_type_id')
            ->map(fn ($rows) => $rows->flatMap(fn ($a) => array_keys(array_filter((array) $a->section_findings)))->unique()->values()->all())
            ->all();
    }

    private function serviceData(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service)],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'default_duration_min' => ['required', 'integer', 'between:10,240'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_bookable_online' => ['boolean'],
            'show_on_website' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);
    }
}
