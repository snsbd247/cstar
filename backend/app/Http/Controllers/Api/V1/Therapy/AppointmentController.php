<?php

namespace App\Http\Controllers\Api\V1\Therapy;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Therapy appointments (THERAPY APPOINTMENT ≠ STUDENT ATTENDANCE). */
class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

    /** GET /appointments?date=|from=&to=&therapist_id=&patient_id=&status= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Appointment::class);
        $request->validate(['status' => ['nullable', Rule::enum(AppointmentStatus::class)]]);
        $user = $request->user();
        $branchIds = $user->accessibleBranchIds();

        $appointments = Appointment::with(['patient', 'service', 'therapist', 'branch', 'session'])
            ->visibleTo($user)
            ->when($branchIds !== null && ! $user->therapist, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->date('date')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->when($request->filled('therapist_id'), fn ($q) => $q->where('therapist_id', $request->integer('therapist_id')))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('appointment_code', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%')->orWhere('patient_code', 'like', '%'.$request->string('q').'%'))))
            ->orderBy('date', $request->boolean('desc') ? 'desc' : 'asc')->orderBy('start_time')
            // The calendar asks for a whole month at once.
            ->paginate(min($request->integer('per_page', 50), 500));

        return AppointmentResource::collection($appointments);
    }

    /** GET /availability?therapist_id=&branch_id=&date=&service_id= */
    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        Gate::authorize(Permission::APPOINTMENTS_VIEW);
        $data = $request->validate([
            'therapist_id' => ['required', 'integer', 'exists:therapists,id'],
            'branch_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ]);

        $duration = isset($data['service_id']) ? (Service::find($data['service_id'])->default_duration_min ?? 45) : 45;

        return response()->json(['data' => $availability->slots(Therapist::findOrFail($data['therapist_id']), $data['branch_id'], Carbon::parse($data['date']), $duration)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'therapist_id' => ['required', 'integer', 'exists:therapists,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'type' => ['nullable', Rule::in(Appointment::TYPES)],
            'status' => ['nullable', Rule::in(['pending', 'confirmed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'appointment_request_id' => ['nullable', 'integer', 'exists:appointment_requests,id'],
        ]);
        Gate::authorize('create', [Appointment::class, (int) $data['branch_id']]);

        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless(Patient::visibleTo($request->user())->whereKey($patient->id)->exists(), 403);

        $appointment = $this->appointments->book($patient, $data, $request->user());

        return (new AppointmentResource($appointment->load(['patient', 'service', 'therapist', 'branch'])))->response()->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        Gate::authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['patient', 'service', 'therapist', 'branch', 'session']));
    }

    /** POST /appointments/{id}/{confirm|check-in|cancel|no-show} */
    public function changeStatus(Request $request, Appointment $appointment, string $action): AppointmentResource
    {
        Gate::authorize(in_array($action, ['check-in', 'no-show'], true) ? 'attend' : 'manage', $appointment);
        $data = $request->validate(['reason' => [$action === 'cancel' ? 'required' : 'nullable', 'string', 'max:255']],
            ['reason.required' => 'Give a reason for the cancellation.']);

        $this->appointments->changeStatus($appointment, $action, $data, $request->user());

        return new AppointmentResource($appointment->fresh(['patient', 'service', 'therapist', 'branch', 'session']));
    }

    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('manage', $appointment);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        $new = $this->appointments->reschedule($appointment, $data['date'], $data['start_time'], $request->user());

        return (new AppointmentResource($new->load(['patient', 'service', 'therapist', 'branch'])))->response()->setStatusCode(201);
    }
}
