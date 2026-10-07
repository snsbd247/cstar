<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money received (type payment) or paid back (type refund). Unallocated payment money is the child's advance. */
#[Fillable([
    'receipt_no', 'patient_id', 'branch_id', 'type', 'amount', 'method', 'transaction_ref', 'paid_at', 'received_by',
    'payer_name', 'notes', 'status', 'void_reason', 'voided_at', 'voided_by',
])]
class Payment extends Model
{
    use Auditable;

    /** Methods staff can choose when taking money. */
    public const METHODS = ['cash', 'bkash', 'nagad', 'bank', 'card'];

    /** Paid by the family through SSLCommerz (card / bKash / Nagad / Rocket) — settles to the bank later (Sprint 19). */
    public const ONLINE = 'online';

    public const ALL_METHODS = [...self::METHODS, self::ONLINE];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
