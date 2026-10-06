<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message staff sent to a group of parents or staff (e.g. "Center closed on Thursday"). */
#[Fillable(['title', 'body', 'audience', 'filters', 'recipients_count', 'sent_by'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
