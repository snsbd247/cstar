<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** A booked therapy slot with a therapist (THERAPY APPOINTMENT ≠ STUDENT ATTENDANCE). */
#[Fillable([
    'appointment_code', 'patient_id', 'enrollment_id', 'service_id', 'therapist_id', 'branch_id', 'date',
    'start_time', 'end_time', 'type', 'status', 'source', 'notes', 'cancel_reason', 'is_late_cancellation',
    'rescheduled_from_id', 'appointment_request_id', 'confirmed_at', 'checked_in_at', 'cancelled_at', 'created_by',
])]
class Appointment extends Model
{
    use Auditable;

    public const TYPES = ['assessment', 'therapy', 'consultation', 'follow_up'];

    /** Cancelling closer than this to the start counts as a late cancellation (decision D3). */
    public const LATE_CANCEL_HOURS = 24;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AppointmentStatus::class,
            'is_late_cancellation' => 'boolean',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function session(): HasOne
    {
        return $this->hasOne(TherapySession::class);
    }

    public function startsAt(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->start_time);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::live());
    }

    /** Appointments of patients the user may see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('patient', fn ($p) => $p->visibleTo($user));
    }
}
