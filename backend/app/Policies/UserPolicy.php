<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/** Super Admin is allowed everything via Gate::before; these rules apply to everyone else. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::USERS_VIEW, Permission::USERS_MANAGE]);
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || ($this->viewAny($user) && $this->sharesBranch($user, $target));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::USERS_MANAGE);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(Permission::USERS_MANAGE)
            && $this->sharesBranch($user, $target)
            && $this->onlyHoldsAssignableRoles($target);
    }

    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->update($user, $target);
    }

    private function sharesBranch(User $user, User $target): bool
    {
        $mine = $user->accessibleBranchIds() ?? [];

        return $target->branches->pluck('id')->intersect($mine)->isNotEmpty();
    }

    /** A Branch Admin may not touch Super Admins or other Branch Admins. */
    private function onlyHoldsAssignableRoles(User $target): bool
    {
        $assignable = collect(Role::assignableByBranchAdmin())->map->value;

        return $target->getRoleNames()->diff($assignable)->isEmpty();
    }
}
