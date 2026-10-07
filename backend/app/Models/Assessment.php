<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** A clinical assessment. Findings are clinical; recommendations are what reception acts on. */
#[Fillable([
    'assessment_code', 'patient_id', 'assessment_type_id', 'appointment_id', 'therapist_id', 'branch_id', 'date',
    'chief_complaint', 'background', 'section_findings', 'summary', 'recommendations', 'parent_summary',
    'status', 'shared_with_parent', 'finalized_at', 'created_by',
])]
class Assessment extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'section_findings' => 'array',
            'shared_with_parent' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class, 'assessment_type_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function recommendationItems(): HasMany
    {
        return $this->hasMany(AssessmentRecommendation::class);
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    /** Supervisor reviews (Sprint 22). */
    public function reviews(): MorphMany
    {
        return $this->morphMany(ClinicalReview::class, 'reviewable');
    }
}
