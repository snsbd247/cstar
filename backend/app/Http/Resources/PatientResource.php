<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** List-level patient data. PatientDetailResource adds the profile page fields. @mixin \App\Models\Patient */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type();

        return [
            'id' => $this->id,
            'patient_code' => $this->patient_code,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'has_photo' => $this->photo_path !== null,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'age' => $this->age,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'status' => $this->status,
            'type' => $type->value,
            'type_label' => $type->label(),
            'registration_date' => $this->registration_date->toDateString(),
            'home_branch' => $this->whenLoaded('homeBranch', fn () => [
                'id' => $this->homeBranch->id, 'name' => $this->homeBranch->name, 'code' => $this->homeBranch->code,
            ]),
            'primary_guardian' => $this->whenLoaded('guardians', function () {
                $guardian = $this->guardians->firstWhere('pivot.is_primary', true) ?? $this->guardians->first();

                return $guardian ? [
                    'id' => $guardian->id, 'name' => $guardian->name, 'phone' => $guardian->phone,
                    'relationship' => $guardian->pivot->relationship,
                ] : null;
            }),
        ];
    }
}
