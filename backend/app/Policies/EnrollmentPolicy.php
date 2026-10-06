<?php

namespace App\Policies;

use App\Enums\Permission;
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
        return $user->can(Permission::ENROLLMENTS_VIEW)
            && Patient::visibleTo($user)->whereKey($enrollment->patient_id)->exists();
    }

    /** Status changes, transfers, notes: needs manage permission and the enrollment's branch. */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::ENROLLMENTS_MANAGE)
            && $user->canAccessBranch($enrollment->branch_id)
            && Patient::visibleTo($user)->whereKey($enrollment->patient_id)->exists();
    }
}
