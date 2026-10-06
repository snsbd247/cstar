<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ServiceCategory;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Therapy appointments (Plan §১৬–১৮): booking, status changes, reschedule and recurring weekly slots.
 */
class AppointmentService
{
    public function __construct(
        private AvailabilityService $availability,
        private IdGenerator $ids,
        private TimelineService $timeline,
        private ChargeService $charges,
    ) {}

    public function book(Patient $patient, array $data, User $user): Appointment
    {
        $service = Service::findOrFail($data['service_id']);
        if ($service->category === ServiceCategory::Training) {
            throw ValidationException::withMessages(['service_id' => 'Regular training is attended by class, not booked as an appointment.']);
        }

        $therapist = Therapist::findOrFail($data['therapist_id']);
        if (! $therapist->isActive() || ! $therapist->provides($service->id)) {
            throw ValidationException::withMessages(['therapist_id' => "{$therapist->name} does not provide {$service->name}."]);
        }

        // Link to the child's open therapy enrollment for this service, if any.
        $enrollment = Enrollment::where('patient_id', $patient->id)->where('type', EnrollmentType::Therapy)
            ->whereIn('status', EnrollmentStatus::open())
            ->whereHas('therapyEnrollment', fn ($q) => $q->where('service_id', $service->id))
            ->with('therapyEnrollment')->first();

        $duration = $data['duration_min'] ?? $enrollment?->therapyEnrollment->session_duration_min ?? $service->default_duration_min ?? 45;
        $start = Carbon::parse($data['date'].' '.$data['start_time']);
        $end = $start->copy()->addMinutes($duration);

        return $this->create($patient, $service, $therapist, $enrollment, (int) $data['branch_id'], $start, $end, [
            'type' => $data['type'] ?? ($enrollment ? 'therapy' : 'assessment'),
            'status' => $data['status'] ?? AppointmentStatus::Confirmed->value,
            'source' => $data['source'] ?? 'front_desk',
            'notes' => $data['notes'] ?? null,
            'appointment_request_id' => $data['appointment_request_id'] ?? null,
        ], $user);
    }

    /** confirm | check-in | cancel | no-show */
    public function changeStatus(Appointment $appointment, string $action, array $data, User $user): Appointment
    {
        [$from, $to] = AppointmentStatus::transitions()[$action] ?? throw ValidationException::withMessages(['action' => 'Unknown action.']);

        if (! in_array($appointment->status, $from, true)) {
            throw ValidationException::withMessages(['status' => "Cannot {$action} an appointment that is ".str_replace('_', ' ', $appointment->status->value).'.']);
        }
        if (in_array($action, ['check-in', 'no-show'], true) && $appointment->date->isFuture()) {
            throw ValidationException::withMessages(['status' => 'This can only be done on or after the appointment day.']);
        }

        $changes = ['status' => $to];
        match ($action) {
            'confirm' => $changes['confirmed_at'] = now(),
            'check-in' => $changes['checked_in_at'] = now(),
            'cancel' => $changes += [
                'cancelled_at' => now(),
                'cancel_reason' => $data['reason'] ?? null,
                // Decision D3: cancelling within 24 hours counts as a late cancellation (used by package rules).
                'is_late_cancellation' => now()->diffInHours($appointment->startsAt(), false) < Appointment::LATE_CANCEL_HOURS,
            ],
            default => null,
        };

        $appointment->update($changes);

        if (in_array($action, ['cancel', 'no-show'], true)) {
            $this->timeline->record($appointment->patient, "appointment.{$to->value}",
                ucfirst(str_replace('_', ' ', $to->value)).": {$appointment->service->name} on {$appointment->date->format('d M')}", $appointment,
                description: $data['reason'] ?? null, branchId: $appointment->branch_id, visibility: 'parent');
            $this->charges->appointmentMissed($appointment, $user);
        }

        return $appointment;
    }

    public function reschedule(Appointment $appointment, string $date, string $startTime, User $user): Appointment
    {
        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending or confirmed appointments can be rescheduled.']);
        }

        $minutes = (int) Carbon::parse($appointment->start_time)->diffInMinutes(Carbon::parse($appointment->end_time));
        $start = Carbon::parse("$date $startTime");

        return DB::transaction(function () use ($appointment, $start, $minutes, $user) {
            $appointment->update(['status' => AppointmentStatus::Rescheduled, 'cancelled_at' => now()]);

            return $this->create($appointment->patient, $appointment->service, $appointment->therapist, $appointment->enrollment,
                $appointment->branch_id, $start, $start->copy()->addMinutes($minutes), [
                    'type' => $appointment->type,
                    'status' => AppointmentStatus::Confirmed->value,
                    'source' => $appointment->source,
                    'notes' => $appointment->notes,
                    'rescheduled_from_id' => $appointment->id,
                ], $user);
        });
    }

    /**
     * Creates appointments for the enrollment's weekly slots over the next weeks.
     * Days that are holidays, leave, taken or already booked are skipped and reported.
     *
     * @return array{created: int, skipped: list<string>}
     */
    public function generateRecurring(Enrollment $enrollment, User $user, int $weeks = 4): array
    {
        $enrollment->loadMissing(['therapyEnrollment.service', 'therapyEnrollment.therapist', 'slots', 'patient']);
        $therapy = $enrollment->therapyEnrollment;
        if (! $therapy || $enrollment->status !== EnrollmentStatus::Active || $enrollment->slots->isEmpty()) {
            return ['created' => 0, 'skipped' => []];
        }

        $duration = $therapy->session_duration_min ?? $therapy->service->default_duration_min ?? 45;
        $created = 0;
        $skipped = [];

        for ($day = today()->addDay(); $day->lte(today()->addWeeks($weeks)); $day->addDay()) {
            $slot = $enrollment->slots->firstWhere('weekday', $day->dayOfWeek);
            if (! $slot || ($enrollment->end_date && $day->gt($enrollment->end_date))) {
                continue;
            }
            $exists = Appointment::live()->where('enrollment_id', $enrollment->id)->whereDate('date', $day)->exists();
            if ($exists) {
                continue;
            }

            $start = $day->copy()->setTimeFromTimeString($slot->start_time);
            try {
                $this->create($enrollment->patient, $therapy->service, $therapy->therapist, $enrollment, $enrollment->branch_id,
                    $start, $start->copy()->addMinutes($duration), ['type' => 'therapy', 'status' => 'confirmed', 'source' => 'recurring'], $user, timeline: false);
                $created++;
            } catch (ValidationException $e) {
                $skipped[] = $day->format('D d M').': '.collect($e->errors())->flatten()->first();
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function create(Patient $patient, Service $service, Therapist $therapist, ?Enrollment $enrollment, int $branchId,
        Carbon $start, Carbon $end, array $extra, User $user, bool $timeline = true): Appointment
    {
        $this->availability->assertBookable($therapist, $branchId, $start, $end, $patient->id, $extra['rescheduled_from_id'] ?? null);

        try {
            $appointment = DB::transaction(function () use ($patient, $service, $therapist, $enrollment, $branchId, $start, $end, $extra, $user) {
                $appointment = Appointment::create([
                    ...$extra,
                    'appointment_code' => $this->ids->next('appointment', 'APT'),
                    'patient_id' => $patient->id,
                    'enrollment_id' => $enrollment?->id,
                    'service_id' => $service->id,
                    'therapist_id' => $therapist->id,
                    'branch_id' => $branchId,
                    'date' => $start->toDateString(),
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $end->format('H:i:s'),
                    'confirmed_at' => ($extra['status'] ?? null) === 'confirmed' ? now() : null,
                    'created_by' => $user->id,
                ]);

                if (! empty($extra['appointment_request_id'])) {
                    AppointmentRequest::whereKey($extra['appointment_request_id'])->update([
                        'appointment_id' => $appointment->id, 'patient_id' => $patient->id, 'status' => 'converted',
                        'handled_by' => $user->id, 'handled_at' => now(),
                    ]);
                }

                return $appointment;
            });
        } catch (UniqueConstraintViolationException) {
            // Another booking won the race for this exact slot.
            throw ValidationException::withMessages(['start_time' => 'This slot was just booked by someone else. Please choose another time.']);
        }

        if ($timeline) {
            $this->timeline->record($patient, 'appointment.booked',
                "Appointment: {$service->name} with {$therapist->name}, {$start->format('d M, g:i A')}", $appointment,
                branchId: $branchId, visibility: 'parent');
        }

        return $appointment;
    }
}
