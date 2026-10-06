<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'guardian_id', 'type', 'granted', 'signed_on', 'document_id', 'recorded_by'])]
class Consent extends Model
{
    use Auditable;

    public const TYPES = ['treatment', 'photo_media', 'data_sharing'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'signed_on' => 'date',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
