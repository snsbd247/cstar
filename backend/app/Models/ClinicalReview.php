<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A supervisor's review of a finalized therapy note or assessment (Sprint 22). */
#[Fillable(['reviewable_type', 'reviewable_id', 'reviewer_id', 'outcome', 'comment'])]
class ClinicalReview extends Model
{
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function toRow(): array
    {
        return ['id' => $this->id, 'outcome' => $this->outcome, 'comment' => $this->comment, 'by' => $this->reviewer?->name, 'at' => $this->created_at?->toIso8601String()];
    }
}
