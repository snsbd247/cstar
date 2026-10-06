<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Farhana',
            'email' => 'farhana@cstar.test',
            'phone' => '01811111111',
            'password' => 'Secret1234',
            'role' => Role::Therapist->value,
            'branch_ids' => [],
            ...$overrides,
        ];
    }

    public function test_branch_admin_can_create_staff_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $branchAdmin = $this->userWithRole(Role::BranchAdmin, $branch);

        $this->actingAs($branchAdmin)
            ->postJson('/api/v1/users', $this->payload(['branch_ids' => [$branch->id]]))
            ->assertCreated()
            ->assertJsonPath('data.primary_role', 'therapist')
            ->assertJsonPath('data.home_path', '/therapist');
    }

    public function test_parent_role_creates_parent_type_user(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->userWithRole(Role::SuperAdmin, $branch);

        $this->actingAs($admin)
            ->postJson('/api/v1/users', $this->payload([
                'email' => null, 'role' => Role::Parent->value, 'branch_ids' => [$branch->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.user_type', 'parent');
    }

    public function test_branch_admin_cannot_create_super_admin(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))
            ->postJson('/api/v1/users', $this->payload(['role' => Role::SuperAdmin->value, 'branch_ids' => [$branch->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_branch_admin_cannot_assign_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $own))
            ->postJson('/api/v1/users', $this->payload(['branch_ids' => [$other->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch_ids');
    }

    public function test_branch_admin_cannot_edit_a_super_admin_in_same_branch(): void
    {
        $branch = Branch::factory()->create();
        $superAdmin = $this->userWithRole(Role::SuperAdmin, $branch);

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))
            ->putJson("/api/v1/users/{$superAdmin->id}", $this->payload([
                'email' => $superAdmin->email, 'phone' => $superAdmin->phone,
                'role' => Role::Receptionist->value, 'branch_ids' => [$branch->id],
            ]))
            ->assertForbidden();
    }

    public function test_user_list_is_scoped_to_own_branches(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $branchAdmin = $this->userWithRole(Role::BranchAdmin, $own);
        $colleague = $this->userWithRole(Role::Trainer, $own);
        $stranger = $this->userWithRole(Role::Trainer, $other);

        $ids = collect($this->actingAs($branchAdmin)->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($colleague->id));
        $this->assertFalse($ids->contains($stranger->id));
    }

    public function test_trainer_cannot_manage_users(): void
    {
        $this->actingAs($this->userWithRole(Role::Trainer))->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_phone_must_be_a_bangladeshi_mobile_number(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->userWithRole(Role::SuperAdmin, $branch))
            ->postJson('/api/v1/users', $this->payload(['phone' => '12345', 'branch_ids' => [$branch->id]]))
            ->assertJsonValidationErrors('phone');
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole(Role::BranchAdmin);

        $this->actingAs($admin)->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
    }
}
