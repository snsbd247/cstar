<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Close My Cash" (Accounts §৮): what the system expected vs what was counted, handed over to a supervisor. */
#[Fillable([
    'branch_id', 'user_id', 'date', 'expected', 'expected_cash', 'counted_cash', 'denominations', 'difference',
    'reason', 'status', 'received_by', 'received_at', 'journal_entry_id',
])]
class CashClosing extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'date' => 'date', 'expected' => 'array', 'denominations' => 'array', 'received_at' => 'datetime',
            'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
