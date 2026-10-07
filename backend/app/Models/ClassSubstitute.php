<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A trainer covering a class for a few days (Sprint 22). */
#[Fillable(['training_group_id', 'trainer_id', 'date_from', 'date_to', 'reason', 'created_by'])]
class ClassSubstitute extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date'];
    }

    public function scopeActiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $day = ($date ?? today())->toDateString();

        return $query->whereDate('date_from', '<=', $day)->whereDate('date_to', '>=', $day);
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
