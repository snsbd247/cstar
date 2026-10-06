<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_branch(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)
            ->postJson('/api/v1/branches', ['code' => 'dhk', 'name' => 'Dhaka Branch'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'DHK')
            ->assertJsonPath('data.slug', 'dhaka-branch');
    }

    public function test_branch_admin_cannot_create_a_branch(): void
    {
        $this->actingAs($this->userWithRole(Role::BranchAdmin))
            ->postJson('/api/v1/branches', ['code' => 'CTG', 'name' => 'Chattogram'])
            ->assertForbidden();
    }

    public function test_branch_admin_only_sees_and_edits_own_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $branchAdmin = $this->userWithRole(Role::BranchAdmin, $own);

        $this->actingAs($branchAdmin)
            ->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->actingAs($branchAdmin)
            ->putJson("/api/v1/branches/{$own->id}", ['code' => $own->code, 'name' => 'Renamed'])
            ->assertOk();

        $this->actingAs($branchAdmin)
            ->putJson("/api/v1/branches/{$other->id}", ['code' => $other->code, 'name' => 'Hijack'])
            ->assertForbidden();

        $this->actingAs($branchAdmin)->getJson("/api/v1/branches/{$other->id}")->assertForbidden();
    }

    public function test_receptionist_can_view_but_not_edit_branch(): void
    {
        $branch = Branch::factory()->create();
        $receptionist = $this->userWithRole(Role::Receptionist, $branch);

        $this->actingAs($receptionist)->getJson("/api/v1/branches/{$branch->id}")->assertOk();
        $this->actingAs($receptionist)
            ->putJson("/api/v1/branches/{$branch->id}", ['code' => $branch->code, 'name' => 'X'])
            ->assertForbidden();
    }

    public function test_trainer_cannot_list_branches(): void
    {
        $this->actingAs($this->userWithRole(Role::Trainer))->getJson('/api/v1/branches')->assertForbidden();
    }

    public function test_branch_changes_are_audited(): void
    {
        $branch = Branch::factory()->create(['name' => 'Old Name']);
        $admin = $this->userWithRole(Role::SuperAdmin, $branch);

        $this->actingAs($admin)
            ->putJson("/api/v1/branches/{$branch->id}", ['code' => $branch->code, 'name' => 'New Name'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
            'user_id' => $admin->id,
        ]);
    }
}
