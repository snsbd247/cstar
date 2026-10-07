<?php

namespace App\Http\Controllers\Api\V1\Therapy;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentRecommendation;
use App\Models\AssessmentType;
use App\Models\Patient;
use App\Models\TherapySession;
use App\Models\TrainingRecord;
use App\Services\AmendmentService;
use App\Services\AssessmentService;
use App\Services\AuditLogger;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Assessments, their recommendations (what reception enrolls from) and the PDF reports. */
class AssessmentController extends Controller
{
    private const WITH = ['patient', 'type', 'therapist', 'branch', 'recommendationItems.service', 'recommendationItems.enrollment'];

    public function __construct(private AssessmentService $assessments, private PdfService $pdf) {}

    public function types(): JsonResponse
    {
        return response()->json(['data' => AssessmentType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'name_bn', 'sections'])]);
    }

    /** GET /assessments?patient_id=&mine=1&status= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Assessment::class);
        $user = $request->user();

        $assessments = Assessment::with(['patient', 'type', 'therapist', 'branch'])
            ->whereHas('patient', fn ($p) => $p->visibleTo($user))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('therapist_id', $user->therapist?->id ?? 0))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('appointment_id'), fn ($q) => $q->where('appointment_id', $request->integer('appointment_id')))
            ->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AssessmentResource::collection($assessments);
    }

    public function show(Assessment $assessment): AssessmentResource
    {
        Gate::authorize('view', $assessment);
        AuditLogger::log('viewed', $assessment);

        return new AssessmentResource($assessment->load(self::WITH));
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('create', [Assessment::class, $patient]);
        $assessment = $this->assessments->save($patient, $this->validated($request, true), $request->user());

        return (new AssessmentResource($assessment->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('update', $assessment);
        $assessment = $this->assessments->save($assessment->patient, $this->validated($request, false), $request->user(), $assessment);

        return new AssessmentResource($assessment->load(self::WITH));
    }

    public function share(Request $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('update', $assessment);
        $data = $request->validate(['shared' => ['required', 'boolean']]);

        return new AssessmentResource($this->assessments->share($assessment->load(['patient', 'type']), $data['shared'])->load(self::WITH));
    }

    /**
     * GET /patients/{id}/recommendations — what the assessments recommend, for reception to enroll from.
     * No clinical findings here, so enrollment staff can see it without assessments.view.
     */
    public function recommendations(Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize(Permission::ENROLLMENTS_VIEW);

        $items = AssessmentRecommendation::with(['assessment.type', 'assessment.therapist', 'service', 'enrollment'])
            ->whereHas('assessment', fn ($q) => $q->where('patient_id', $patient->id)->where('status', 'final'))
            ->get()
            ->sortByDesc(fn ($r) => $r->assessment->date)
            ->values();

        return response()->json(['data' => $items->map(fn ($r) => [
            'id' => $r->id,
            'assessment' => ['id' => $r->assessment_id, 'code' => $r->assessment->assessment_code, 'date' => $r->assessment->date->toDateString(),
                'type' => $r->assessment->type->name, 'therapist' => $r->assessment->therapist->only(['id', 'name'])],
            'enrollment_type' => $r->enrollment_type,
            'service' => $r->service?->only(['id', 'name']),
            'frequency' => $r->frequency,
            'priority' => $r->priority,
            'note' => $r->note,
            'enrollment' => $r->enrollment?->only(['id', 'enrollment_code', 'status']),
        ])]);
    }

    public function pdf(Assessment $assessment): Response
    {
        Gate::authorize('view', $assessment);
        $assessment->load(['patient.homeBranch', 'patient.guardians', ...self::WITH]);
        AuditLogger::log('exported', $assessment, new: ['format' => 'pdf']);

        $amendments = app(AmendmentService::class)->history($assessment);

        return $this->pdf->response('pdf.assessment', ['a' => $assessment, 'amendments' => $amendments], 'Assessment Report', "{$assessment->assessment_code}.pdf");
    }

    /** GET /patients/{id}/progress-report?from=&to= — plans, goals and session summary for a period. */
    public function progressReport(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize(Permission::PLANS_VIEW);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? today()->subMonths(3))->startOfDay();
        $to = Carbon::parse($data['to'] ?? today())->endOfDay();

        $patient->load([
            'homeBranch',
            'enrollments' => fn ($q) => $q->where(fn ($w) => $w->whereIn('status', ['active', 'on_hold', 'pending'])->orWhere('end_date', '>=', $from)),
            'enrollments.trainingEnrollment.trainingGroup', 'enrollments.trainingEnrollment.trainer',
            'enrollments.therapyEnrollment.service', 'enrollments.therapyEnrollment.therapist',
            'enrollments.plans' => fn ($q) => $q->where(fn ($w) => $w->where('status', 'active')->orWhere('review_date', '>=', $from)),
            'enrollments.plans.goals',
        ]);

        $sessions = TherapySession::with('service')->where('patient_id', $patient->id)->where('status', 'final')
            ->whereBetween('date', [$from, $to])->get()->groupBy(fn ($s) => $s->service->name);
        $records = TrainingRecord::where('patient_id', $patient->id)->whereBetween('date', [$from, $to])->count();
        $assessments = Assessment::with('type')->where('patient_id', $patient->id)->where('status', 'final')
            ->whereBetween('date', [$from, $to])->get();

        AuditLogger::log('exported', $patient, new: ['report' => 'progress', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return $this->pdf->response('pdf.progress', compact('patient', 'from', 'to', 'sessions', 'records', 'assessments'),
            'Progress Report', "{$patient->patient_code}-progress.pdf");
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'assessment_type_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('assessment_types', 'id')->where('is_active', true)],
            'appointment_id' => ['nullable', 'integer', Rule::unique('assessments', 'appointment_id')->ignore($request->route('assessment'))],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'chief_complaint' => ['nullable', 'string', 'max:3000'],
            'background' => ['nullable', 'string', 'max:5000'],
            'section_findings' => ['nullable', 'array'],
            'section_findings.*' => ['nullable', 'string', 'max:5000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'parent_summary' => ['nullable', 'string', 'max:3000'],
            'recommendation_items' => ['sometimes', 'array', 'max:10'],
            'recommendation_items.*.enrollment_type' => ['required', Rule::in(['training', 'therapy'])],
            'recommendation_items.*.service_id' => ['nullable', 'required_if:recommendation_items.*.enrollment_type,therapy', 'integer', 'exists:services,id'],
            'recommendation_items.*.frequency' => ['nullable', 'string', 'max:100'],
            'recommendation_items.*.priority' => ['nullable', Rule::in(['high', 'normal', 'low'])],
            'recommendation_items.*.note' => ['nullable', 'string', 'max:1000'],
            'finalize' => ['boolean'],
        ], ['appointment_id.unique' => 'This appointment already has an assessment.']);
    }
}
