<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base enrollment. Exactly one extension row exists, matching `type`:
 * training → TrainingEnrollment (class + trainer), therapy → TherapyEnrollment (service + therapist).
 */
#[Fillable([
    'enrollment_code', 'patient_id', 'branch_id', 'type', 'status', 'start_date', 'end_date',
    'end_reason', 'end_note', 'notes', 'created_by',
])]
class Enrollment extends Model
{
    use Auditable, SoftDeletes;

    public const END_REASONS = ['goals_achieved', 'dropout', 'transferred_out', 'financial', 'relocated', 'other'];

    protected function casts(): array
    {
        return [
            'type' => EnrollmentType::class,
            'status' => EnrollmentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function trainingEnrollment(): HasOne
    {
        return $this->hasOne(TrainingEnrollment::class);
    }

    public function therapyEnrollment(): HasOne
    {
        return $this->hasOne(TherapyEnrollment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EnrollmentAssignment::class)->latest('from_date')->latest('id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(IndividualPlan::class)->latest('start_date')->latest('id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTraining(): bool
    {
        return $this->type === EnrollmentType::Training;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', EnrollmentStatus::open());
    }

    /** Enrollments of patients the user may see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('patient', fn ($p) => $p->visibleTo($user));
    }

    /** Human description, e.g. "Speech & Language Therapy with Imran". */
    public function summary(): string
    {
        if ($this->isTraining()) {
            $t = $this->trainingEnrollment;

            return "Regular Training · {$t->trainingGroup->name} · Trainer {$t->trainer->name}";
        }

        $t = $this->therapyEnrollment;

        return "{$t->service->name} · Therapist {$t->therapist->name}";
    }
}
