<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An online booking request from the website. Becomes a patient only after reception verifies it. */
#[Fillable([
    'reference', 'branch_id', 'service_id', 'preferred_therapist_id', 'parent_name', 'child_name',
    'child_age_years', 'phone', 'email', 'preferred_date', 'preferred_time', 'message', 'status',
    'internal_note', 'patient_id', 'handled_by', 'handled_at', 'source', 'ip_address',
])]
class AppointmentRequest extends Model
{
    use Auditable;

    public const STATUSES = ['new', 'contacted', 'converted', 'rejected', 'spam'];

    public const TIMES = [
        'morning' => 'Morning (9am - 12pm)',
        'afternoon' => 'Afternoon (12pm - 3pm)',
        'evening' => 'Evening (3pm - 6pm)',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'handled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function preferredTherapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class, 'preferred_therapist_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleBranchIds();

        return $ids === null ? $query : $query->whereIn('branch_id', $ids);
    }
}
