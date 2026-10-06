<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'patient_id', 'diagnosis_notes', 'medical_history', 'developmental_history',
    'previous_therapy', 'medications', 'allergies', 'school_info',
])]
class PatientClinicalProfile extends Model
{
    use Auditable;

    public const FIELDS = [
        'diagnosis_notes', 'medical_history', 'developmental_history',
        'previous_therapy', 'medications', 'allergies', 'school_info',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
