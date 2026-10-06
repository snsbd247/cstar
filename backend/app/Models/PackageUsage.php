<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ledger of package sessions used (+1) or given back (−1); `value` is the revenue recognised. */
#[Fillable(['patient_package_id', 'appointment_id', 'therapy_session_id', 'reason', 'quantity', 'value', 'note', 'recorded_by'])]
class PackageUsage extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
