<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Manual voucher (Accounts §৪): draft → submitted → posted, or rejected. Posted vouchers are only reversed. */
#[Fillable([
    'voucher_no', 'type', 'date', 'branch_id', 'narration', 'amount', 'status', 'attachment_path', 'reject_reason',
    'journal_entry_id', 'prepared_by', 'approved_by', 'submitted_at', 'approved_at',
])]
class Voucher extends Model
{
    use Auditable;

    /** Prefixes for the voucher number. */
    public const TYPES = ['payment' => 'PV', 'receipt' => 'RV', 'journal' => 'JV', 'contra' => 'CV'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class)->orderBy('sort_order');
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

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }
}
