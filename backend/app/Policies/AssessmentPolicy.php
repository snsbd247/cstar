<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\User;

/** Assessments are clinical: written by therapists, read by clinical staff. Super Admin passes via Gate::before. */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ASSESSMENTS_VIEW);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $user->can(Permission::ASSESSMENTS_VIEW) && Patient::visibleTo($user)->whereKey($assessment->patient_id)->exists();
    }

    /** A therapist assesses a child they can see (therapy enrollment or an upcoming/recent appointment). */
    public function create(User $user, Patient $patient): bool
    {
        return $user->can(Permission::ASSESSMENTS_WRITE)
            && $user->therapist !== null
            && Patient::visibleTo($user)->whereKey($patient->id)->exists();
    }

    /** Only the assessing therapist edits, finalizes or shares the assessment. */
    public function update(User $user, Assessment $assessment): bool
    {
        return $user->can(Permission::ASSESSMENTS_WRITE) && $assessment->therapist_id === $user->therapist?->id;
    }
}
