<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part of a payment applied to an invoice. `from_advance` = applied later from money held as advance. */
#[Fillable(['payment_id', 'invoice_id', 'amount', 'from_advance'])]
class PaymentAllocation extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'from_advance' => 'boolean'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
