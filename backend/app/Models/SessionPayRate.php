<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a therapist earns per finalized session — per service, or for any service when service_id is null. */
#[Fillable(['employee_id', 'service_id', 'rate', 'effective_from'])]
class SessionPayRate extends Model
{
    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'effective_from' => 'date'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
