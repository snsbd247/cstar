<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'name', 'category', 'unit', 'stock', 'reorder_level', 'unit_cost', 'is_active', 'notes'])]
class InventoryItem extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'therapy_material' => 'Therapy material', 'toy' => 'Toys & games', 'stationery' => 'Stationery',
        'cleaning' => 'Cleaning & hygiene', 'medical' => 'First aid / medical', 'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['stock' => 'decimal:2', 'reorder_level' => 'decimal:2', 'unit_cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function isLow(): bool
    {
        return (float) $this->reorder_level > 0 && (float) $this->stock <= (float) $this->reorder_level;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
