<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\PatientStatus;
use App\Enums\PatientType;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The single "person" record for every child (PATIENT ≠ STUDENT).
 * Whether the child is a student, a therapy patient or both is derived from enrollments.
 */
#[Fillable([
    'patient_code', 'home_branch_id', 'name', 'name_bn', 'photo_path', 'date_of_birth', 'gender',
    'father_name', 'mother_name', 'phone', 'alt_phone', 'email', 'address',
    'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
    'referral_source', 'referred_by', 'registration_date', 'status', 'notes', 'created_by',
])]
class Patient extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'registration_date' => 'date',
            'status' => PatientStatus::class,
        ];
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function clinicalProfile(): HasOne
    {
        return $this->hasOne(PatientClinicalProfile::class);
    }

    public function diagnoses(): BelongsToMany
    {
        return $this->belongsToMany(Diagnosis::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary', 'is_emergency_contact', 'can_access_portal'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    /** Age in years and months, e.g. "5y 3m". */
    protected function age(): Attribute
    {
        return Attribute::get(function () {
            $diff = $this->date_of_birth->diff(now());

            return "{$diff->y}y {$diff->m}m";
        });
    }

    /** Adds active_training_count / active_therapy_count used by type(). */
    public function scopeWithEnrollmentCounts(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as active_training_count' => fn ($q) => $q->where('type', EnrollmentType::Training)->whereIn('status', EnrollmentStatus::current()),
            'enrollments as active_therapy_count' => fn ($q) => $q->where('type', EnrollmentType::Therapy)->whereIn('status', EnrollmentStatus::current()),
        ]);
    }

    public function type(): PatientType
    {
        // Use the withEnrollmentCounts() values when loaded, otherwise query.
        $attributes = $this->getAttributes();
        $training = $attributes['active_training_count']
            ?? $this->enrollments()->where('type', EnrollmentType::Training)->whereIn('status', EnrollmentStatus::current())->count();
        $therapy = $attributes['active_therapy_count']
            ?? $this->enrollments()->where('type', EnrollmentType::Therapy)->whereIn('status', EnrollmentStatus::current())->count();

        return PatientType::fromCounts($training, $therapy);
    }

    public function scopeOfType(Builder $query, PatientType $type): Builder
    {
        $has = fn (EnrollmentType $t) => fn ($q) => $q->where('type', $t)->whereIn('status', EnrollmentStatus::current());

        return match ($type) {
            PatientType::Student => $query->whereHas('enrollments', $has(EnrollmentType::Training))->whereDoesntHave('enrollments', $has(EnrollmentType::Therapy)),
            PatientType::TherapyPatient => $query->whereHas('enrollments', $has(EnrollmentType::Therapy))->whereDoesntHave('enrollments', $has(EnrollmentType::Training)),
            PatientType::StudentAndTherapy => $query->whereHas('enrollments', $has(EnrollmentType::Training))->whereHas('enrollments', $has(EnrollmentType::Therapy)),
            PatientType::Registered => $query->whereDoesntHave('enrollments', fn ($q) => $q->whereIn('status', EnrollmentStatus::current())),
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('name_bn', 'like', "%{$term}%")
            ->orWhere('patient_code', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', "%{$term}%")));
    }

    /**
     * THE access rule for patient records. Every patient query and the PatientPolicy go through here.
     *  - Super Admin: everyone
     *  - Branch Admin / Receptionist / Accountant: home branch or an enrollment in their branches
     *  - Trainer: children in their open training enrollments or classes they lead
     *  - Therapist: children in their open therapy enrollments
     *  - Parent: their own children with portal access
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $roles = $user->getRoleNames();
        $branchIds = $user->accessibleBranchIds() ?? [];
        $open = EnrollmentStatus::open();

        return $query->where(function (Builder $q) use ($user, $roles, $branchIds, $open) {
            $q->whereRaw('1 = 0');

            if ($roles->intersect([Role::BranchAdmin->value, Role::Receptionist->value, Role::Accountant->value])->isNotEmpty()) {
                $q->orWhereIn('home_branch_id', $branchIds)
                    ->orWhereHas('enrollments', fn ($e) => $e->whereIn('branch_id', $branchIds));
            }

            if ($roles->contains(Role::Trainer->value) && ($trainerId = $user->trainer?->id)) {
                $q->orWhereHas('enrollments', fn ($e) => $e
                    ->where('type', EnrollmentType::Training)
                    ->whereIn('status', $open)
                    ->whereHas('trainingEnrollment', fn ($t) => $t->where(fn ($w) => $w
                        ->where('trainer_id', $trainerId)
                        ->orWhereHas('trainingGroup', fn ($g) => $g->where('lead_trainer_id', $trainerId)
                            // A substitute trainer sees the class's children while covering it (Sprint 22).
                            ->orWhereHas('substitutes', fn ($s) => $s->where('trainer_id', $trainerId)->activeOn())))));
            }

            if ($roles->contains(Role::Therapist->value) && ($therapistId = $user->therapist?->id)) {
                $q->orWhereHas('enrollments', fn ($e) => $e
                    ->where('type', EnrollmentType::Therapy)
                    ->whereIn('status', $open)
                    ->whereHas('therapyEnrollment', fn ($t) => $t->where('therapist_id', $therapistId)))
                    // A first assessment has no enrollment yet: an upcoming or recent appointment also grants access.
                    ->orWhereHas('appointments', fn ($a) => $a
                        ->where('therapist_id', $therapistId)
                        ->whereIn('status', AppointmentStatus::live())
                        ->whereDate('date', '>=', today()->subDays(14)))
                    // The assessing therapist keeps access to the child they assessed.
                    ->orWhereHas('assessments', fn ($a) => $a->where('therapist_id', $therapistId));

                // A clinical supervisor reviews every therapy child of their branches (Sprint 22, Plan #২০).
                if ($user->therapist?->is_supervisor) {
                    $q->orWhereHas('enrollments', fn ($e) => $e->where('type', EnrollmentType::Therapy)->whereIn('status', $open)->whereIn('branch_id', $branchIds))
                        ->orWhereHas('assessments', fn ($a) => $a->whereIn('branch_id', $branchIds));
                }
            }

            if ($roles->contains(Role::Parent->value) && ($guardianId = $user->guardian?->id)) {
                $q->orWhereHas('guardians', fn ($g) => $g
                    ->where('guardians.id', $guardianId)
                    ->where('guardian_patient.can_access_portal', true));
            }
        });
    }
}
