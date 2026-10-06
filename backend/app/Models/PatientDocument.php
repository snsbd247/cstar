<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stored on the private disk only; downloaded through an authorised controller, never a public URL. */
#[Fillable([
    'patient_id', 'category', 'title', 'path', 'original_name', 'mime', 'size', 'visible_to_parent', 'uploaded_by',
])]
class PatientDocument extends Model
{
    use Auditable, SoftDeletes;

    public const CATEGORIES = ['medical_report', 'prescription', 'previous_assessment', 'consent_form', 'id_document', 'other'];

    /** Categories that contain clinical information (need patients.view_clinical). */
    public const CLINICAL_CATEGORIES = ['medical_report', 'prescription', 'previous_assessment'];

    protected function casts(): array
    {
        return ['visible_to_parent' => 'boolean'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isClinical(): bool
    {
        return in_array($this->category, self::CLINICAL_CATEGORIES, true);
    }
}
