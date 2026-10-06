<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\EnrollmentStatusRequest;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\TransferEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Enrollment::class);

        $request->validate([
            'type' => ['nullable', Rule::enum(EnrollmentType::class)],
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
        ]);

        $enrollments = Enrollment::query()
            ->visibleTo($request->user())
            ->with([
                'patient', 'branch',
                'trainingEnrollment.trainingGroup', 'trainingEnrollment.trainer',
                'therapyEnrollment.service', 'therapyEnrollment.therapist',
            ])
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->latest('start_date')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return EnrollmentResource::collection($enrollments);
    }

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $patient = Patient::findOrFail($request->integer('patient_id'));
        Gate::authorize('enroll', $patient);

        $enrollment = $this->enrollments->create($patient, $request->validated(), $request->user());

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(201);
    }

    public function show(Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $enrollment);

        return new EnrollmentResource($this->enrollments->load($enrollment));
    }

    /** POST /enrollments/{id}/{activate|hold|resume|complete|discontinue} */
    public function changeStatus(EnrollmentStatusRequest $request, Enrollment $enrollment, string $action): EnrollmentResource
    {
        Gate::authorize('update', $enrollment);

        return new EnrollmentResource($this->enrollments->changeStatus($enrollment, $action, $request->validated()));
    }

    public function transfer(TransferEnrollmentRequest $request, Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('update', $enrollment);

        return new EnrollmentResource($this->enrollments->transfer($enrollment, $request->validated(), $request->user()));
    }
}
