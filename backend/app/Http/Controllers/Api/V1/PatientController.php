<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PatientType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\PatientRequest;
use App\Http\Resources\PatientDetailResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\TimelineEventResource;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\PatientService;
use App\Services\ProgressChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function __construct(private PatientService $patients) {}

    /** Receptionist quick search + filters: ?search= (name / ID / phone) &type= &status= &branch_id= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Patient::class);

        $request->validate([
            'type' => ['nullable', Rule::enum(PatientType::class)],
            'branch_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'max:100'],
        ]);

        $patients = Patient::query()
            ->visibleTo($request->user())
            ->withEnrollmentCounts()
            ->with(['homeBranch', 'guardians'])
            ->search($request->input('search'))
            ->when($request->filled('type'), fn ($q) => $q->ofType(PatientType::from($request->input('type'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where(fn ($q) => $q
                ->where('home_branch_id', $request->integer('branch_id'))
                ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $request->integer('branch_id')))))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        $patient = $this->patients->register($request->validated(), $request->user());

        return $this->detail($request, $patient)->response()->setStatusCode(201);
    }

    public function show(Request $request, Patient $patient): PatientDetailResource
    {
        Gate::authorize('view', $patient);

        return $this->detail($request, $patient);
    }

    public function update(PatientRequest $request, Patient $patient): PatientDetailResource
    {
        Gate::authorize('update', $patient);

        $this->patients->update($patient, $request->validated(), $request->user()->can('viewClinical', $patient));

        return $this->detail($request, $patient);
    }

    /** Warn the receptionist before registering the same child twice. */
    public function checkDuplicates(Request $request): JsonResponse
    {
        Gate::authorize('create', Patient::class);

        $matches = $this->patients->findDuplicates(
            $request->input('phone'), $request->input('date_of_birth'), $request->input('name'), $request->integer('except_id') ?: null,
        );

        return response()->json(['data' => $matches->map(fn (Patient $p) => [
            'id' => $p->id,
            'patient_code' => $p->patient_code,
            'name' => $p->name,
            'date_of_birth' => $p->date_of_birth->toDateString(),
            'branch' => $p->homeBranch?->name,
        ])]);
    }

    public function photo(Patient $patient): StreamedResponse
    {
        Gate::authorize('view', $patient);
        abort_unless($patient->photo_path && Storage::disk('local')->exists($patient->photo_path), 404);

        return Storage::disk('local')->response($patient->photo_path, headers: ['Cache-Control' => 'private, max-age=300']);
    }

    public function uploadPhoto(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('update', $patient);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        $this->patients->storePhoto($patient, $request->file('photo'));

        return response()->json(['message' => 'Photo updated.']);
    }

    /** GET /patients/{patient}/progress-chart — six months of attendance, performance, sessions and goal scores (Sprint 20). */
    public function progressChart(Patient $patient, ProgressChartService $charts): JsonResponse
    {
        Gate::authorize('view', $patient);

        return response()->json(['data' => $charts->for($patient)]);
    }

    public function timeline(Request $request, Patient $patient): AnonymousResourceCollection
    {
        Gate::authorize('view', $patient);

        return TimelineEventResource::collection(
            $patient->timelineEvents()->with('actor')->latest('occurred_at')->latest('id')->paginate(30),
        );
    }

    private function detail(Request $request, Patient $patient): PatientDetailResource
    {
        $patient->load([
            'homeBranch', 'guardians', 'consents',
            'enrollments' => fn ($q) => $q->latest('start_date')->latest('id'),
            'enrollments.branch',
            'enrollments.trainingEnrollment.trainingGroup', 'enrollments.trainingEnrollment.trainer',
            'enrollments.therapyEnrollment.service', 'enrollments.therapyEnrollment.therapist',
            'enrollments.assignments.trainer', 'enrollments.assignments.trainingGroup', 'enrollments.assignments.therapist',
        ]);

        if ($request->user()->can('viewClinical', $patient)) {
            $patient->load(['clinicalProfile', 'diagnoses']);
            AuditLogger::log('viewed', $patient, new: ['section' => 'clinical']);
        }

        return new PatientDetailResource($patient);
    }
}
