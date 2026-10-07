<?php

namespace App\Http\Controllers\Api\V1\Therapy;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\TherapySessionResource;
use App\Models\Appointment;
use App\Models\Enrollment;
use App\Models\TherapySession;
use App\Services\AppointmentService;
use App\Services\SystemSettings;
use App\Services\TherapySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Therapy session notes, the therapist's day, and recurring weekly slots. */
class TherapySessionController extends Controller
{
    public function __construct(private TherapySessionService $sessions) {}

    /** GET /appointments/{id}/session — the note plus what the therapist needs to write it. */
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('viewSession', $appointment);
        $appointment->load(['patient', 'service', 'therapist', 'branch', 'session.activities', 'session.goalScores']);

        $previous = TherapySession::with('practiceLogs')->where('patient_id', $appointment->patient_id)->where('service_id', $appointment->service_id)
            ->where('status', 'final')->where('appointment_id', '!=', $appointment->id)->latest('date')->first();

        return response()->json(['data' => [
            'appointment' => new AppointmentResource($appointment),
            'session' => $appointment->session ? new TherapySessionResource($appointment->session) : null,
            'previous' => $previous ? ['date' => $previous->date->toDateString(), 'next_session_plan' => $previous->next_session_plan, 'home_practice' => $previous->home_practice,
                'practice_feedback' => $previous->practiceLogs->sortByDesc('date')->values()->map(fn ($l) => ['date' => $l->date->toDateString(), 'status' => $l->status, 'comment' => $l->comment])] : null,
            'can_write' => $request->user()->can('writeSession', $appointment),
        ]]);
    }

    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('writeSession', $appointment);

        $data = $request->validate([
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'goals_worked' => ['nullable', 'string', 'max:3000'],
            'observation' => ['nullable', 'string', 'max:5000'],
            'patient_response' => ['nullable', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:5000'],
            'challenges' => ['nullable', 'string', 'max:5000'],
            'home_practice' => ['nullable', 'string', 'max:3000'],
            'next_session_plan' => ['nullable', 'string', 'max:3000'],
            'therapist_notes' => ['nullable', 'string', 'max:5000'],
            'parent_summary' => ['nullable', 'string', 'max:3000'],
            'activity_ids' => ['array'],
            'activity_ids.*' => ['integer', 'exists:activity_types,id'],
            'goal_scores' => ['array'],
            'goal_scores.*.goal_id' => ['required', 'integer'],
            'goal_scores.*.score' => ['required', 'integer', 'between:1,5'],
            'goal_scores.*.note' => ['nullable', 'string', 'max:500'],
            'finalize' => ['boolean'],
        ]);

        if (! empty($data['finalize']) && blank($data['parent_summary'] ?? null)) {
            throw ValidationException::withMessages(['parent_summary' => 'Add a short summary for the parents before finalizing.']);
        }

        $session = $this->sessions->save($appointment, $data, $request->user());

        return (new TherapySessionResource($session))->response()->setStatusCode($session->wasRecentlyCreated ? 201 : 200);
    }

    /** GET /therapy-sessions?patient_id=&therapist_id=&status= — history for children the user may see. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::THERAPY_SESSIONS_VIEW);

        $sessions = TherapySession::with(['patient', 'therapist', 'service', 'activities'])
            ->whereHas('patient', fn ($p) => $p->visibleTo($request->user()))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('therapist_id'), fn ($q) => $q->where('therapist_id', $request->integer('therapist_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return TherapySessionResource::collection($sessions);
    }

    /** GET /therapist/today — Plan §১৪: today's appointments and notes still to finalize. */
    public function today(Request $request): JsonResponse
    {
        $therapist = $request->user()->therapist;
        abort_unless($therapist, 403, 'This account is not linked to a therapist profile.');

        $appointments = Appointment::with(['patient', 'service', 'branch', 'session'])
            ->where('therapist_id', $therapist->id)->whereDate('date', today())
            ->whereNotIn('status', ['rescheduled'])->orderBy('start_time')->get();

        $pending = Appointment::with(['patient', 'service'])->where('therapist_id', $therapist->id)
            ->whereDate('date', '<', today())->whereDate('date', '>=', today()->subDays(14))
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->where(fn ($q) => $q->whereDoesntHave('session')->orWhereHas('session', fn ($s) => $s->where('status', 'draft')))
            ->orderByDesc('date')->get();

        return response()->json(['data' => [
            'date' => today()->toDateString(),
            'therapist' => $therapist->only(['id', 'name']),
            'appointments' => AppointmentResource::collection($appointments),
            'notes_pending' => AppointmentResource::collection($pending),
            'totals' => [
                'appointments' => $appointments->whereNotIn('status.value', ['cancelled', 'no_show'])->count(),
                'checked_in' => $appointments->where('status.value', 'checked_in')->count(),
                'completed' => $appointments->where('status.value', 'completed')->count(),
                'notes_pending' => $pending->count() + $appointments->where('status.value', 'checked_in')->count(),
            ],
        ]]);
    }

    /** GET /therapist/patients — children in the therapist's open therapy enrollments. */
    public function myPatients(Request $request): JsonResponse
    {
        $therapist = $request->user()->therapist;
        abort_unless($therapist, 403);

        $enrollments = Enrollment::with(['patient', 'therapyEnrollment.service'])
            ->where('type', EnrollmentType::Therapy)->whereIn('status', EnrollmentStatus::open())
            ->whereHas('therapyEnrollment', fn ($t) => $t->where('therapist_id', $therapist->id))->get();

        return response()->json(['data' => $enrollments->map(fn (Enrollment $e) => [
            'enrollment_id' => $e->id,
            'status' => $e->status,
            'service' => $e->therapyEnrollment->service->only(['id', 'name']),
            'patient' => [
                'id' => $e->patient->id, 'name' => $e->patient->name, 'patient_code' => $e->patient->patient_code,
                'age' => $e->patient->age, 'has_photo' => $e->patient->photo_path !== null,
            ],
        ])->sortBy('patient.name')->values()]);
    }

    /** GET/PUT /enrollments/{id}/slots — regular weekly times of a therapy enrollment. */
    public function slots(Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('view', $enrollment);

        return response()->json(['data' => $enrollment->slots->map(fn ($s) => ['weekday' => $s->weekday, 'start_time' => substr($s->start_time, 0, 5)])]);
    }

    public function updateSlots(Request $request, Enrollment $enrollment): JsonResponse
    {
        Gate::authorize('update', $enrollment);
        abort_if($enrollment->isTraining(), 422, 'Regular training follows the class schedule.');

        $data = $request->validate([
            'slots' => ['present', 'array', 'max:7'],
            'slots.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
        ]);

        $enrollment->slots()->delete();
        $enrollment->slots()->createMany($data['slots']);

        return response()->json(['message' => 'Weekly slots saved.']);
    }

    /** POST /enrollments/{id}/generate-appointments — book the next weeks from the weekly slots. */
    public function generate(Request $request, Enrollment $enrollment, AppointmentService $appointments): JsonResponse
    {
        Gate::authorize('update', $enrollment);
        $weeks = $request->validate(['weeks' => ['nullable', 'integer', 'between:1,12']])['weeks'] ?? app(SystemSettings::class)->int('appointment', 'recurring_weeks');

        return response()->json(['data' => $appointments->generateRecurring($enrollment, $request->user(), $weeks)]);
    }
}
