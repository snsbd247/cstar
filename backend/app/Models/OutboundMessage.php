<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel', 'driver', 'to', 'body', 'segments', 'kind', 'user_id', 'status', 'provider_ref', 'error', 'response', 'sent_at'])]
class OutboundMessage extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
