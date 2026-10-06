<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A package bought by a child. Remaining sessions are always derivable from package_usages. */
#[Fillable([
    'patient_id', 'package_id', 'service_id', 'enrollment_id', 'invoice_item_id', 'branch_id',
    'total_sessions', 'used_sessions', 'price', 'start_date', 'expiry_date', 'status',
])]
class PatientPackage extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'start_date' => 'date', 'expiry_date' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total_sessions - $this->used_sessions);
    }

    /** Revenue for the next session; the last session takes the rounding remainder so the total equals the price. */
    public function valueOfNextSession(): float
    {
        $unit = round((float) $this->price / $this->total_sessions, 2);

        return $this->used_sessions + 1 >= $this->total_sessions
            ? round((float) $this->price - (float) $this->usages()->sum('value'), 2)
            : $unit;
    }

    /** Value not yet recognised as income. */
    public function unearnedValue(): float
    {
        return max(0, round((float) $this->price - (float) $this->usages()->sum('value'), 2));
    }
}
