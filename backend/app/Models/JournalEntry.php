<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A balanced double-entry journal entry. Auto-posted entries are never edited — only reversed. */
#[Fillable([
    'voucher_no', 'voucher_type', 'event', 'date', 'branch_id', 'fiscal_year_id', 'accounting_period_id',
    'narration', 'source_type', 'source_id', 'status', 'reversal_of_id', 'prepared_by', 'approved_by', 'posted_at',
])]
class JournalEntry extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
