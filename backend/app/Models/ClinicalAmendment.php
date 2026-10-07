<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One correction to a finalized clinical record; rows are never changed or deleted. */
#[Fillable(['amendable_type', 'amendable_id', 'field', 'old_value', 'new_value', 'reason', 'amended_by'])]
class ClinicalAmendment extends Model
{
    public const UPDATED_AT = null;

    public function amendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id, 'field' => $this->field, 'old_value' => $this->old_value, 'new_value' => $this->new_value,
            'reason' => $this->reason, 'by' => $this->author?->name, 'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
