<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** One student's record in a class sitting, written by the trainer. Locked once final. */
#[Fillable([
    'training_session_id', 'enrollment_id', 'patient_id', 'trainer_id', 'date', 'start_time', 'end_time',
    'duration_min', 'goals_worked', 'observation', 'performance', 'progress', 'challenges',
    'trainer_notes', 'parent_note', 'next_plan', 'status', 'finalized_at',
])]
class TrainingRecord extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
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
