<?php

namespace App\Http\Resources;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guardian */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'nid' => $this->nid,
            'address' => $this->address,
            'has_portal_account' => $this->user_id !== null,
            'relationship' => $this->whenPivotLoaded('guardian_patient', fn () => $this->pivot->relationship),
            'is_primary' => $this->whenPivotLoaded('guardian_patient', fn () => (bool) $this->pivot->is_primary),
            'is_emergency_contact' => $this->whenPivotLoaded('guardian_patient', fn () => (bool) $this->pivot->is_emergency_contact),
            'can_access_portal' => $this->whenPivotLoaded('guardian_patient', fn () => (bool) $this->pivot->can_access_portal),
        ];
    }
}
