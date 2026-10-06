<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** draft → issued → partially_paid → paid; or void (never deleted once issued). */
#[Fillable([
    'invoice_no', 'patient_id', 'branch_id', 'guardian_id', 'issue_date', 'due_date', 'subtotal', 'discount_total',
    'total', 'paid_total', 'due_total', 'status', 'discount_reason', 'notes', 'void_reason', 'voided_at', 'voided_by',
    'issued_by', 'created_by',
])]
class Invoice extends Model
{
    use Auditable;

    public const OPEN = ['issued', 'partially_paid'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'voided_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'total' => 'decimal:2',
            'paid_total' => 'decimal:2', 'due_total' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
