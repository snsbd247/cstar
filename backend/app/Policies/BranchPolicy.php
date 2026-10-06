<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

/** Super Admin is allowed everything via Gate::before; these rules apply to everyone else. */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::BRANCHES_VIEW, Permission::BRANCHES_MANAGE]);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($branch->id);
    }

    /** Opening or closing a branch is a Super Admin decision. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::BRANCHES_MANAGE) && $user->canAccessBranch($branch->id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return false;
    }
}
