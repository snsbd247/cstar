<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier, landlord or utility the center buys from (Accounts §৬). */
#[Fillable(['name', 'type', 'phone', 'address', 'opening_balance', 'is_active', 'notes'])]
class Vendor extends Model
{
    use Auditable;

    public const TYPES = ['landlord', 'supplier', 'utility', 'service', 'other'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }
}
