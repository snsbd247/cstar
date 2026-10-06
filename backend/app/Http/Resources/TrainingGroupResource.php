<?php

namespace App\Http\Resources;

use App\Models\TrainingGroupSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A class. @mixin \App\Models\TrainingGroup */
class TrainingGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'max_students' => $this->max_students,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'notes' => $this->notes,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'lead_trainer' => $this->whenLoaded('leadTrainer', fn () => $this->leadTrainer ? ['id' => $this->leadTrainer->id, 'name' => $this->leadTrainer->name] : null),
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'weekday' => $s->weekday,
                'day' => TrainingGroupSchedule::DAYS[$s->weekday],
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
            ])),
            'students_count' => $this->whenHas('students_count'),
        ];
    }
}
