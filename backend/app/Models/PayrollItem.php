<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee's line in a payroll run — becomes their payslip. */
#[Fillable([
    'payroll_run_id', 'employee_id', 'department', 'fixed_amount', 'session_count', 'session_pay', 'bonus', 'other_addition',
    'absence_deduction', 'gross', 'advance_deduction', 'tax', 'other_deduction', 'net_pay', 'breakdown', 'note',
    'paid_amount', 'paid_at', 'payment_journal_id',
])]
class PayrollItem extends Model
{
    public const ADJUSTABLE = ['bonus', 'other_addition', 'absence_deduction', 'advance_deduction', 'tax', 'other_deduction', 'note'];

    protected function casts(): array
    {
        return [
            'breakdown' => 'array', 'paid_at' => 'datetime',
            'fixed_amount' => 'decimal:2', 'session_pay' => 'decimal:2', 'bonus' => 'decimal:2', 'other_addition' => 'decimal:2',
            'absence_deduction' => 'decimal:2', 'gross' => 'decimal:2', 'advance_deduction' => 'decimal:2', 'tax' => 'decimal:2',
            'other_deduction' => 'decimal:2', 'net_pay' => 'decimal:2', 'paid_amount' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** gross = earnings − absence; net = gross − advance − tax − other deductions. */
    public function recalculate(): void
    {
        $this->gross = round((float) $this->fixed_amount + (float) $this->session_pay + (float) $this->bonus + (float) $this->other_addition - (float) $this->absence_deduction, 2);
        $this->net_pay = round((float) $this->gross - (float) $this->advance_deduction - (float) $this->tax - (float) $this->other_deduction, 2);
    }
}
