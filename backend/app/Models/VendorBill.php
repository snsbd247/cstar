<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A purchase on credit: Dr expense/asset, Cr Accounts Payable. */
#[Fillable([
    'bill_no', 'vendor_ref', 'vendor_id', 'branch_id', 'date', 'due_date', 'total', 'paid', 'status',
    'description', 'attachment_path', 'journal_entry_id', 'created_by',
])]
class VendorBill extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['date' => 'date', 'due_date' => 'date', 'total' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function due(): float
    {
        return round((float) $this->total - (float) $this->paid, 2);
    }
}
