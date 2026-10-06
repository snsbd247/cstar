<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_code' => $this->appointment_code,
            'date' => $this->date->toDateString(),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'type' => $this->type,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'is_late_cancellation' => $this->is_late_cancellation,
            'enrollment_id' => $this->enrollment_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id, 'name' => $this->patient->name, 'patient_code' => $this->patient->patient_code,
                'phone' => $this->patient->phone, 'has_photo' => $this->patient->photo_path !== null,
            ]),
            'service' => $this->whenLoaded('service', fn () => $this->service->only(['id', 'name'])),
            'therapist' => $this->whenLoaded('therapist', fn () => $this->therapist->only(['id', 'name'])),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->only(['id', 'name'])),
            'session' => $this->whenLoaded('session', fn () => $this->session ? ['id' => $this->session->id, 'status' => $this->session->status] : null),
            'checked_in_at' => $this->checked_in_at,
        ];
    }
}
