<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A therapy package for sale, e.g. "Speech Therapy — 12 sessions, valid 60 days". */
#[Fillable(['name', 'name_bn', 'service_id', 'sessions_count', 'validity_days', 'price', 'branch_id', 'description', 'is_active'])]
class Package extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
