<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Furniture, therapy equipment, computers — straight-line depreciation each month (Accounts §১০). */
#[Fillable([
    'asset_code', 'name', 'account_id', 'branch_id', 'location', 'purchase_date', 'cost', 'salvage_value',
    'useful_life_months', 'accumulated_depreciation', 'depreciated_until', 'status', 'disposed_at', 'disposal_amount',
    'notes', 'journal_entry_id', 'created_by',
])]
class FixedAsset extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'disposed_at' => 'date', 'cost' => 'decimal:2', 'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2', 'disposal_amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function monthlyDepreciation(): float
    {
        return round(((float) $this->cost - (float) $this->salvage_value) / max(1, $this->useful_life_months), 2);
    }

    public function bookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }

    /** Still to depreciate before reaching the salvage value. */
    public function depreciable(): float
    {
        return max(0, round((float) $this->cost - (float) $this->salvage_value - (float) $this->accumulated_depreciation, 2));
    }
}
