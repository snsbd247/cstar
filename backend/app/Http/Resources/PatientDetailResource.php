<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Models\PatientClinicalProfile;
use Illuminate\Http\Request;

/**
 * Patient profile page. Clinical data (clinical_profile, diagnoses) appears only when the
 * controller loaded it — which it does only for users allowed to see it.
 *
 * @mixin Patient
 */
class PatientDetailResource extends PatientResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'alt_phone' => $this->alt_phone,
            'email' => $this->email,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'referral_source' => $this->referral_source,
            'referred_by' => $this->referred_by,
            'notes' => $this->notes,
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'enrollments' => EnrollmentResource::collection($this->whenLoaded('enrollments')),
            'consents' => $this->whenLoaded('consents', fn () => $this->consents->map(fn ($c) => [
                'type' => $c->type, 'granted' => $c->granted, 'signed_on' => $c->signed_on->toDateString(),
            ])),
            'clinical_profile' => $this->whenLoaded('clinicalProfile', fn () => $this->clinicalProfile?->only(PatientClinicalProfile::FIELDS)),
            'diagnoses' => $this->whenLoaded('diagnoses', fn () => $this->diagnoses->map->only(['id', 'name'])),
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'view_clinical' => $request->user()->can('viewClinical', $this->resource),
                'manage_guardians' => $request->user()->can('manageGuardians', $this->resource),
                'enroll' => $request->user()->can('enroll', $this->resource),
            ],
        ];
    }
}
