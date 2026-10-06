<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Monthly pay components from a date; a raise is a new row, so old payslips stay explainable. */
#[Fillable([
    'employee_id', 'effective_from', 'basic', 'house_rent', 'medical', 'conveyance', 'other_allowances',
    'included_sessions', 'revenue_share_percent', 'created_by',
])]
class SalaryStructure extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date', 'other_allowances' => 'array',
            'basic' => 'decimal:2', 'house_rent' => 'decimal:2', 'medical' => 'decimal:2', 'conveyance' => 'decimal:2',
            'revenue_share_percent' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return array<string, float> component name => amount */
    public function components(): array
    {
        $parts = ['Basic' => (float) $this->basic, 'House rent' => (float) $this->house_rent, 'Medical' => (float) $this->medical, 'Conveyance' => (float) $this->conveyance];
        foreach ($this->other_allowances ?? [] as $a) {
            $parts[$a['name']] = (float) $a['amount'];
        }

        return array_filter($parts, fn ($v) => $v > 0);
    }

    public function monthlyTotal(): float
    {
        return round(array_sum($this->components()), 2);
    }
}
