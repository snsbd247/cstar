<?php

namespace App\Http\Resources;

use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TherapySession */
class TherapySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'enrollment_id' => $this->enrollment_id,
            'date' => $this->date->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'duration_min' => $this->duration_min,
            ...$this->only(TherapySession::TEXT_FIELDS),
            'status' => $this->status,
            'finalized_at' => $this->finalized_at,
            'updated_at' => $this->updated_at,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient->only(['id', 'name', 'patient_code'])),
            'therapist' => $this->whenLoaded('therapist', fn () => $this->therapist->only(['id', 'name'])),
            'service' => $this->whenLoaded('service', fn () => $this->service->only(['id', 'name'])),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map->only(['id', 'name'])),
            'goal_scores' => $this->whenLoaded('goalScores', fn () => $this->goalScores->map(fn ($g) => ['goal_id' => $g->plan_goal_id, 'score' => $g->score, 'note' => $g->note])),
        ];
    }
}
