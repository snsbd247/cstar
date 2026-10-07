<?php

namespace App\Http\Resources;

use App\Models\TrainingRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainingRecord */
class TrainingRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'date' => $this->date->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'duration_min' => $this->duration_min,
            'goals_worked' => $this->goals_worked,
            'observation' => $this->observation,
            'performance' => $this->performance,
            'progress' => $this->progress,
            'challenges' => $this->challenges,
            'trainer_notes' => $this->trainer_notes,
            'parent_note' => $this->parent_note,
            'next_plan' => $this->next_plan,
            'status' => $this->status,
            'finalized_at' => $this->finalized_at,
            'can_amend' => $this->status === 'final' && $request->user()?->trainer?->id === $this->trainer_id,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient->only(['id', 'name', 'patient_code'])),
            'trainer' => $this->whenLoaded('trainer', fn () => $this->trainer?->only(['id', 'name'])),
            'class' => $this->whenLoaded('session', fn () => $this->session->relationLoaded('trainingGroup') ? $this->session->trainingGroup->only(['id', 'name']) : null),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map->only(['id', 'name'])),
            'goal_scores' => $this->whenLoaded('goalScores', fn () => $this->goalScores->map(fn ($g) => ['goal_id' => $g->plan_goal_id, 'score' => $g->score, 'note' => $g->note])),
        ];
    }
}
