<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\TrainingGroup;
use App\Models\User;

/**
 * Classes. Trainers act only on classes they teach; office staff act on classes of their branches.
 * Super Admin passes via Gate::before.
 */
class TrainingGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CLASSES_VIEW);
    }

    public function view(User $user, TrainingGroup $group): bool
    {
        return $user->can(Permission::CLASSES_VIEW) && $this->reaches($user, $group);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CLASSES_MANAGE);
    }

    public function update(User $user, TrainingGroup $group): bool
    {
        return $user->can(Permission::CLASSES_MANAGE) && $user->canAccessBranch($group->branch_id);
    }

    public function markAttendance(User $user, TrainingGroup $group): bool
    {
        return $user->can(Permission::TRAINING_ATTENDANCE_MARK) && $this->reaches($user, $group);
    }

    public function viewAttendance(User $user, TrainingGroup $group): bool
    {
        return $user->can(Permission::TRAINING_ATTENDANCE_VIEW) && $this->reaches($user, $group);
    }

    public function writeRecords(User $user, TrainingGroup $group): bool
    {
        return $user->can(Permission::TRAINING_RECORDS_WRITE) && $this->reaches($user, $group);
    }

    /** A trainer must teach the class; everyone else needs the class's branch. */
    private function reaches(User $user, TrainingGroup $group): bool
    {
        if ($user->hasRole(Role::Trainer->value) && ! $user->hasAnyRole([Role::BranchAdmin->value, Role::Receptionist->value])) {
            return $group->isTaughtBy($user->trainer);
        }

        return $user->canAccessBranch($group->branch_id);
    }
}
