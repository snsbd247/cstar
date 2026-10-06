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
use App\Models\EnrollmentAssignment;
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
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('enrollment_code', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%')->orWhere('patient_code', 'like', '%'.$request->string('q').'%'))))
            ->latest('start_date')->latest('id')
            ->paginate($request->integer('per_page', 20));

        // Counts per status for the filter chips (same visibility and type filter, any status).
        $counts = Enrollment::query()->visibleTo($request->user())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return EnrollmentResource::collection($enrollments)->additional(['counts' => $counts]);
    }

    /** GET /enrollment-transfers — class / trainer / therapist changes, newest first (Enrollments → Transfer History). */
    public function transfers(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Enrollment::class);
        $visible = Enrollment::query()->visibleTo($request->user())->select('id');

        $rows = EnrollmentAssignment::with(['enrollment.patient', 'enrollment.branch', 'trainer', 'trainingGroup', 'therapist', 'createdBy'])
            ->whereIn('enrollment_id', $visible)
            // Every enrollment starts with one assignment; each later one is a transfer.
            ->whereExists(fn ($q) => $q->from('enrollment_assignments as first')->whereColumn('first.enrollment_id', 'enrollment_assignments.enrollment_id')->whereColumn('first.id', '<', 'enrollment_assignments.id'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('from_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('from_date', '<=', $request->date('to')))
            ->latest('from_date')->latest('id')
            ->paginate($request->integer('per_page', 30));

        $previous = EnrollmentAssignment::with(['trainer', 'trainingGroup', 'therapist'])
            ->whereIn('enrollment_id', collect($rows->items())->pluck('enrollment_id'))->orderBy('id')->get()->groupBy('enrollment_id');
        $describe = fn (?EnrollmentAssignment $a) => $a ? collect([$a->trainingGroup?->name, $a->trainer?->name, $a->therapist?->name])->filter()->implode(' · ') : null;

        return response()->json([
            'data' => collect($rows->items())->map(fn (EnrollmentAssignment $a) => [
                'id' => $a->id,
                'date' => $a->from_date->toDateString(),
                'enrollment' => ['id' => $a->enrollment->id, 'code' => $a->enrollment->enrollment_code, 'type' => $a->enrollment->type->value, 'summary' => $a->enrollment->type->label()],
                'patient' => $a->enrollment->patient->only(['id', 'name', 'patient_code']),
                'branch' => $a->enrollment->branch->name,
                'from' => $describe($previous[$a->enrollment_id]->where('id', '<', $a->id)->last()),
                'to' => $describe($a),
                'reason' => $a->reason,
                'by' => $a->createdBy?->name,
            ]),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
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
