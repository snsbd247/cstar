<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Roles & permissions exist in every test that uses RefreshDatabase. */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    /** Create an active user holding $role, attached to the given branches (a new branch if none). */
    protected function userWithRole(Role $role, Branch ...$branches): User
    {
        $user = $role === Role::Parent ? User::factory()->parent()->create() : User::factory()->create();
        $user->assignRole($role->value);

        $branches = $branches ?: [Branch::factory()->create()];
        $user->branches()->sync(collect($branches)->pluck('id'));

        return $user->fresh();
    }

    /** Headers that make Sanctum treat the request as coming from the React SPA (session cookie auth). */
    protected function spaHeaders(): array
    {
        return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
    }
}
