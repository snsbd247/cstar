<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** July–June fiscal year (decision A1). */
#[Fillable(['name', 'start_date', 'end_date', 'status'])]
class FiscalYear extends Model
{
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }
}
