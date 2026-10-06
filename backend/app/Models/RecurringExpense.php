<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rent, internet, security… raised as a draft expense on the same day every month for the accountant to check. */
#[Fillable([
    'branch_id', 'expense_category_id', 'amount', 'day_of_month', 'paid_from_account_id', 'payee', 'description',
    'is_active', 'last_generated_period', 'created_by',
])]
class RecurringExpense extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }
}
