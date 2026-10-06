<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'account_id', 'annual_amount'])]
class BudgetLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['annual_amount' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
