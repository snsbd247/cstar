<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\PlanGoal;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Therapy session notes (Plan §১৮). Written by the treating therapist on/after the appointment day;
 * finalizing locks the note, completes the appointment and shares the parent summary.
 */
class TherapySessionService
{
    public function __construct(private TimelineService $timeline, private ChargeService $charges) {}

    public function save(Appointment $appointment, array $data, User $user): TherapySession
    {
        $appointment->loadMissing(['service', 'patient', 'session']);

        if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Rescheduled], true)) {
            throw ValidationException::withMessages(['appointment' => 'This appointment did not take place.']);
        }
        if ($appointment->date->isFuture()) {
            throw ValidationException::withMessages(['appointment' => 'A session note can be written on or after the appointment day.']);
        }
        if ($appointment->session?->isFinal()) {
            throw ValidationException::withMessages(['session' => 'This session note is final and can no longer be changed.']);
        }

        return DB::transaction(function () use ($appointment, $data, $user) {
            $finalize = ! empty($data['finalize']);
            $start = $data['start_time'] ?? substr($appointment->start_time, 0, 5);
            $end = $data['end_time'] ?? substr($appointment->end_time, 0, 5);

            $session = TherapySession::updateOrCreate(['appointment_id' => $appointment->id], [
                ...Arr::only($data, TherapySession::TEXT_FIELDS),
                'enrollment_id' => $appointment->enrollment_id,
                'patient_id' => $appointment->patient_id,
                'therapist_id' => $appointment->therapist_id,
                'service_id' => $appointment->service_id,
                'branch_id' => $appointment->branch_id,
                'date' => $appointment->date,
                'start_time' => $start,
                'end_time' => $end,
                'duration_min' => (int) abs(Carbon::parse($end)->diffInMinutes(Carbon::parse($start))),
                'status' => $finalize ? 'final' : 'draft',
                'finalized_at' => $finalize ? now() : null,
            ]);

            $session->activities()->sync($data['activity_ids'] ?? []);
            $this->saveGoalScores($session, $data['goal_scores'] ?? [], $user);

            if ($appointment->status !== AppointmentStatus::Completed && in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
                $appointment->update(['status' => AppointmentStatus::CheckedIn, 'checked_in_at' => $appointment->checked_in_at ?? now()]);
            }

            if ($finalize) {
                $appointment->update(['status' => AppointmentStatus::Completed]);
                $this->timeline->record($appointment->patient, 'therapy.session', "Therapy session — {$appointment->service->name}", $session,
                    description: $session->parent_summary, branchId: $appointment->branch_id, visibility: 'parent');
                // Billing: use a package session, or charge the session.
                $this->charges->sessionCompleted($appointment, $session, $user);
            }

            return $session->load(['activities', 'goalScores']);
        });
    }

    private function saveGoalScores(TherapySession $session, array $scores, User $user): void
    {
        $allowed = $session->enrollment_id
            ? PlanGoal::whereHas('plan', fn ($q) => $q->where('enrollment_id', $session->enrollment_id)->where('status', 'active'))->pluck('id')
            : collect();

        $session->goalScores()->delete();
        foreach ($scores as $i => $score) {
            if (! $allowed->contains($score['goal_id'])) {
                throw ValidationException::withMessages(["goal_scores.$i.goal_id" => 'This goal is not part of the child\'s active therapy plan.']);
            }
            $session->goalScores()->create([
                'plan_goal_id' => $score['goal_id'], 'date' => $session->date, 'score' => $score['score'],
                'note' => $score['note'] ?? null, 'recorded_by' => $user->id,
            ]);
        }
    }
}
