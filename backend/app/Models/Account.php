<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A ledger account in the chart of accounts. System accounts (system_key) are used by auto-posting. */
#[Fillable([
    'code', 'name', 'name_bn', 'type', 'parent_id', 'is_group', 'normal_balance', 'subtype',
    'system_key', 'branch_id', 'service_id', 'is_system', 'is_active', 'opening_balance',
])]
class Account extends Model
{
    use Auditable;

    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean', 'opening_balance' => 'decimal:2'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
