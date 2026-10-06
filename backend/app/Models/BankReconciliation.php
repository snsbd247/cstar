<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Matching a bank / bKash / Nagad statement against the books up to a date (Accounts §৯). */
#[Fillable(['account_id', 'statement_date', 'statement_balance', 'status', 'book_balance', 'created_by', 'completed_by', 'completed_at'])]
class BankReconciliation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'book_balance' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('date')->orderBy('id');
    }
}
