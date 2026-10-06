<?php

namespace App\Services;

use App\Enums\EnrollmentType;
use App\Models\Enrollment;
use App\Models\PlanGoal;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingRecord;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daily training records (Plan §১১, §১৭). Rules:
 *  - only for a student of this class who was present or late that day;
 *  - a final record is locked (no edits);
 *  - goal scores must belong to the student's active plan.
 */
class TrainingRecordService
{
    public function __construct(private TimelineService $timeline) {}

    public function save(TrainingGroup $group, Carbon $date, Enrollment $enrollment, array $data, User $user): TrainingRecord
    {
        $enrollment->loadMissing(['trainingEnrollment', 'patient']);
        $group->loadMissing('schedules');
        $user->loadMissing('trainer');
        $training = $enrollment->trainingEnrollment;
        if ($enrollment->type !== EnrollmentType::Training || $training?->training_group_id !== $group->id) {
            throw ValidationException::withMessages(['enrollment_id' => 'This student is not in this class.']);
        }

        $attendance = TrainingAttendance::where('enrollment_id', $enrollment->id)->whereDate('date', $date)->first();
        if (! $attendance?->status->attended()) {
            throw ValidationException::withMessages(['enrollment_id' => 'Mark the student present (or late) before writing a training record.']);
        }

        return DB::transaction(function () use ($group, $date, $enrollment, $training, $data, $user) {
            $schedule = $group->scheduleFor($date);
            $session = TrainingSession::firstOrCreate(
                ['training_group_id' => $group->id, 'date' => $date->toDateString()],
                [
                    'trainer_id' => $user->trainer?->id ?? $group->lead_trainer_id ?? $training->trainer_id,
                    'branch_id' => $group->branch_id,
                    'start_time' => $schedule?->start_time,
                    'end_time' => $schedule?->end_time,
                ],
            );

            $record = TrainingRecord::firstOrNew(['training_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);
            if ($record->exists && $record->isFinal()) {
                throw ValidationException::withMessages(['record' => 'This record is final and can no longer be changed.']);
            }

            $start = $data['start_time'] ?? $session->start_time;
            $end = $data['end_time'] ?? $session->end_time;
            $record->fill([
                ...Arr::only($data, ['goals_worked', 'observation', 'performance', 'progress', 'challenges', 'trainer_notes', 'parent_note', 'next_plan']),
                'patient_id' => $enrollment->patient_id,
                'trainer_id' => $user->trainer?->id ?? $training->trainer_id,
                'date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'duration_min' => $start && $end ? (int) abs(Carbon::parse($end)->diffInMinutes(Carbon::parse($start))) : null,
                'status' => ! empty($data['finalize']) ? 'final' : 'draft',
                'finalized_at' => ! empty($data['finalize']) ? now() : null,
            ])->save();

            $record->activities()->sync($data['activity_ids'] ?? []);
            $this->saveGoalScores($record, $enrollment, $data['goal_scores'] ?? [], $user);

            if ($record->isFinal()) {
                $this->timeline->record($enrollment->patient, 'training.record', "Training session — {$group->name}", $record,
                    description: $record->parent_note, branchId: $group->branch_id, visibility: 'parent');
            }

            return $record->load(['activities', 'goalScores', 'trainer']);
        });
    }

    /** @param  list<array{goal_id: int, score: int, note?: ?string}>  $scores */
    private function saveGoalScores(TrainingRecord $record, Enrollment $enrollment, array $scores, User $user): void
    {
        $allowed = PlanGoal::whereHas('plan', fn ($q) => $q->where('enrollment_id', $enrollment->id)->where('status', 'active'))->pluck('id');

        $record->goalScores()->delete();
        foreach ($scores as $i => $score) {
            if (! $allowed->contains($score['goal_id'])) {
                throw ValidationException::withMessages(["goal_scores.$i.goal_id" => 'This goal is not part of the student\'s active plan.']);
            }
            $record->goalScores()->create([
                'plan_goal_id' => $score['goal_id'],
                'date' => $record->date,
                'score' => $score['score'],
                'note' => $score['note'] ?? null,
                'recorded_by' => $user->id,
            ]);
        }
    }
}
