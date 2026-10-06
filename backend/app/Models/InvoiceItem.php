<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'invoice_id', 'item_type', 'service_id', 'enrollment_id', 'package_id', 'patient_package_id', 'appointment_id',
    'therapy_session_id', 'billing_period', 'description', 'quantity', 'unit_price', 'discount', 'line_total',
])]
class InvoiceItem extends Model
{
    public const TYPES = ['admission', 'assessment', 'training_fee', 'therapy_session', 'package', 'consultation', 'other'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** The child's package created when this package line was issued. */
    public function patientPackage(): HasOne
    {
        return $this->hasOne(PatientPackage::class);
    }

    public function gross(): float
    {
        return round($this->quantity * (float) $this->unit_price, 2);
    }
}
