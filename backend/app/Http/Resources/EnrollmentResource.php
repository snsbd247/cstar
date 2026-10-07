<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Enrollment */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $training = $this->relationLoaded('trainingEnrollment') ? $this->trainingEnrollment : null;
        $therapy = $this->relationLoaded('therapyEnrollment') ? $this->therapyEnrollment : null;

        return [
            'id' => $this->id,
            'enrollment_code' => $this->enrollment_code,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'status' => $this->status,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'end_reason' => $this->end_reason,
            'end_note' => $this->end_note,
            'notes' => $this->notes,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name, 'code' => $this->branch->code]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'patient_code' => $this->patient->patient_code, 'name' => $this->patient->name,
            ]),
            'training' => $training ? [
                'class' => ['id' => $training->trainingGroup->id, 'name' => $training->trainingGroup->name, 'code' => $training->trainingGroup->code],
                'trainer' => ['id' => $training->trainer->id, 'name' => $training->trainer->name],
                'monthly_fee' => $training->monthly_fee,
            ] : null,
            'therapy' => $therapy ? [
                'service' => ['id' => $therapy->service->id, 'name' => $therapy->service->name],
                'therapist' => ['id' => $therapy->therapist->id, 'name' => $therapy->therapist->name],
                'sessions_per_week' => $therapy->sessions_per_week,
                'session_duration_min' => $therapy->session_duration_min,
                'billing_mode' => $therapy->billing_mode,
            ] : null,
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'from_date' => $a->from_date->toDateString(),
                'to_date' => $a->to_date?->toDateString(),
                'class' => $a->trainingGroup?->name,
                'trainer' => $a->trainer?->name,
                'therapist' => $a->therapist?->name,
                'reason' => $a->reason,
            ])),
        ];
    }
}
