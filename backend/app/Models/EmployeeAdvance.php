<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Salary advance, recovered in instalments from payroll (Accounts §৭.৩). */
#[Fillable(['employee_id', 'date', 'amount', 'installment', 'balance', 'paid_from_account_id', 'reason', 'journal_entry_id', 'status', 'created_by'])]
class EmployeeAdvance extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'installment' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }
}
