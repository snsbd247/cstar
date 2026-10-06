<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: creates any missing permission/role and resets each role to its default permission set.
 * Re-run after adding permissions in a new sprint.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::all() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        // Model events are muted during seeding, so the permission cache must be flushed by hand.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')->syncPermissions($role->defaultPermissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
