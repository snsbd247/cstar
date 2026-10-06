<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A month's salary (or festival bonus) for one branch: draft → posted (approved) → paid. */
#[Fillable([
    'run_no', 'month', 'branch_id', 'type', 'title', 'status', 'total_gross', 'total_deductions', 'total_net',
    'journal_entry_id', 'prepared_by', 'approved_by', 'approved_at', 'notes',
])]
class PayrollRun extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'total_gross' => 'decimal:2', 'total_deductions' => 'decimal:2', 'total_net' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function start(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    public function end(): Carbon
    {
        return $this->start()->endOfMonth();
    }

    public function label(): string
    {
        return $this->title ?: ($this->type === 'bonus' ? 'Bonus' : 'Salary').' — '.$this->start()->format('F Y');
    }
}
