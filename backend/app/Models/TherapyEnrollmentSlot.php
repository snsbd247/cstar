<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A weekly recurring appointment time for a therapy enrollment. */
#[Fillable(['enrollment_id', 'weekday', 'start_time'])]
class TherapyEnrollmentSlot extends Model
{
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
