<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tran_id', 'gateway', 'patient_id', 'branch_id', 'invoice_id', 'user_id', 'amount', 'status', 'gateway_ref', 'gateway_trx',
    'instrument', 'payment_id', 'error', 'raw', 'paid_at',
])]
class OnlinePayment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'raw' => 'array', 'paid_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
