<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One sitting of a class on one day (TRAINING SESSION ≠ THERAPY SESSION). */
#[Fillable(['training_group_id', 'trainer_id', 'branch_id', 'date', 'start_time', 'end_time', 'theme', 'notes'])]
class TrainingSession extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }
}
