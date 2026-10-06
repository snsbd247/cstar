<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::USERS_VIEW);

        $roles = RoleModel::with('permissions')->get()->keyBy('name');

        return response()->json(['data' => collect(Role::byPriority())->map(fn (Role $role) => [
            'name' => $role->value,
            'label' => $role->label(),
            'editable' => $role !== Role::SuperAdmin,
            'assignable_by_branch_admin' => in_array($role, Role::assignableByBranchAdmin(), true),
            'permissions' => $roles->get($role->value)?->permissions->pluck('name')->values() ?? [],
        ])->values()]);
    }

    /** Permission catalogue grouped by module, for the role editor. */
    public function permissions(): JsonResponse
    {
        Gate::authorize(Permission::ROLES_MANAGE);

        return response()->json(['data' => collect(Permission::all())
            ->groupBy(fn (string $p) => str_contains($p, '.') ? explode('.', $p)[0] : $p)]);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        Gate::authorize(Permission::ROLES_MANAGE);

        $enum = Role::tryFrom($role);
        abort_if($enum === null, 404);
        abort_if($enum === Role::SuperAdmin, 422, 'Super Admin permissions cannot be changed.');

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::all())],
        ]);

        RoleModel::findByName($enum->value, 'web')->syncPermissions($validated['permissions']);

        return response()->json(['message' => 'Permissions updated.']);
    }
}
