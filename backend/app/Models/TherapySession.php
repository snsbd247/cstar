<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Clinical note of one therapy appointment (THERAPY SESSION ≠ TRAINING SESSION). Locked once final. */
#[Fillable([
    'appointment_id', 'enrollment_id', 'patient_id', 'therapist_id', 'service_id', 'branch_id', 'date',
    'start_time', 'end_time', 'duration_min', 'goals_worked', 'observation', 'patient_response', 'progress',
    'challenges', 'home_practice', 'next_session_plan', 'therapist_notes', 'parent_summary', 'status', 'finalized_at',
])]
class TherapySession extends Model
{
    use Auditable;

    public const TEXT_FIELDS = [
        'goals_worked', 'observation', 'patient_response', 'progress', 'challenges',
        'home_practice', 'next_session_plan', 'therapist_notes', 'parent_summary',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ActivityType::class);
    }

    public function goalScores(): MorphMany
    {
        return $this->morphMany(GoalProgressEntry::class, 'source');
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }
}
