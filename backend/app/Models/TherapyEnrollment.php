<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enrollment_id', 'service_id', 'therapist_id', 'sessions_per_week', 'session_duration_min', 'billing_mode'])]
class TherapyEnrollment extends Model
{
    public const BILLING_MODES = ['package', 'per_session', 'monthly'];

    protected $primaryKey = 'enrollment_id';

    public $incrementing = false;

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }
}
