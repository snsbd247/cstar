<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ENROLLMENTS_VIEW);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::ENROLLMENTS_VIEW) && $this->patientVisible($user, $enrollment);
    }

    /** Status changes, transfers, notes: needs manage permission and the enrollment's branch. */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::ENROLLMENTS_MANAGE)
            && $user->canAccessBranch($enrollment->branch_id)
            && $this->patientVisible($user, $enrollment);
    }

    public function viewPlans(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::PLANS_VIEW) && $this->patientVisible($user, $enrollment);
    }

    /**
     * Writing the individual plan: the enrollment's own trainer (training) or therapist (therapy),
     * or office staff with plans.write in the enrollment's branch. TRAINER ≠ THERAPIST applies here too.
     */
    public function writePlans(User $user, Enrollment $enrollment): bool
    {
        if (! $user->can(Permission::PLANS_WRITE)) {
            return false;
        }

        if ($user->hasRole(Role::Trainer->value)) {
            return $enrollment->isTraining() && $this->isAssignedTrainer($user, $enrollment);
        }

        if ($user->hasRole(Role::Therapist->value)) {
            return ! $enrollment->isTraining() && $enrollment->therapyEnrollment?->therapist_id === $user->therapist?->id;
        }

        return $user->canAccessBranch($enrollment->branch_id);
    }

    public function viewAttendance(User $user, Enrollment $enrollment): bool
    {
        return $enrollment->isTraining() && $user->can(Permission::TRAINING_ATTENDANCE_VIEW) && $this->patientVisible($user, $enrollment);
    }

    private function isAssignedTrainer(User $user, Enrollment $enrollment): bool
    {
        $trainerId = $user->trainer?->id;
        $training = $enrollment->trainingEnrollment;

        return $trainerId && $training
            && ($training->trainer_id === $trainerId || $training->trainingGroup?->lead_trainer_id === $trainerId);
    }

    private function patientVisible(User $user, Enrollment $enrollment): bool
    {
        return Patient::visibleTo($user)->whereKey($enrollment->patient_id)->exists();
    }
}
