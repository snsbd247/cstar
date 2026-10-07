<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use App\Services\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assessment */
class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_code' => $this->assessment_code,
            'date' => $this->date->toDateString(),
            'status' => $this->status,
            'shared_with_parent' => $this->shared_with_parent,
            'finalized_at' => $this->finalized_at,
            'appointment_id' => $this->appointment_id,
            ...$this->only(AssessmentService::TEXT_FIELDS),
            'section_findings' => $this->section_findings ?? (object) [],
            'can_edit' => $request->user()?->can('update', $this->resource) && ! $this->isFinal(),
            'can_amend' => $request->user()?->can('update', $this->resource) && $this->isFinal(),
            'updated_at' => $this->updated_at,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient->only(['id', 'name', 'patient_code'])),
            'type' => $this->whenLoaded('type', fn () => $this->type->only(['id', 'name', 'name_bn', 'sections'])),
            'therapist' => $this->whenLoaded('therapist', fn () => $this->therapist->only(['id', 'name'])),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->only(['id', 'name'])),
            'recommendation_items' => $this->whenLoaded('recommendationItems', fn () => $this->recommendationItems->map(fn ($r) => [
                'id' => $r->id,
                'enrollment_type' => $r->enrollment_type,
                'service' => $r->service?->only(['id', 'name']),
                'frequency' => $r->frequency,
                'priority' => $r->priority,
                'note' => $r->note,
                'enrollment' => $r->enrollment?->only(['id', 'enrollment_code', 'status']),
            ])),
        ];
    }
}
