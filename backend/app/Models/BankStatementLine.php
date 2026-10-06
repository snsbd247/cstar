<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the bank's statement; + money in, − money out. */
#[Fillable(['bank_reconciliation_id', 'date', 'description', 'reference', 'amount', 'journal_line_id', 'status'])]
class BankStatementLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
