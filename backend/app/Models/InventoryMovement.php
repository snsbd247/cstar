<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_item_id', 'date', 'type', 'quantity', 'balance_after', 'unit_cost', 'reference', 'note', 'created_by'])]
class InventoryMovement extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'quantity' => 'decimal:2', 'balance_after' => 'decimal:2', 'unit_cost' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
