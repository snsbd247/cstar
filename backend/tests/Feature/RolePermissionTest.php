<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /** TRAINER ≠ THERAPIST: each writes only its own kind of session record. */
    public function test_trainer_and_therapist_have_separate_clinical_permissions(): void
    {
        $trainer = $this->userWithRole(Role::Trainer);
        $therapist = $this->userWithRole(Role::Therapist);

        $this->assertTrue($trainer->can(Permission::TRAINING_RECORDS_WRITE));
        $this->assertTrue($trainer->can(Permission::TRAINING_ATTENDANCE_MARK));
        $this->assertFalse($trainer->can(Permission::THERAPY_SESSIONS_WRITE));
        $this->assertFalse($trainer->can(Permission::ASSESSMENTS_WRITE));

        $this->assertTrue($therapist->can(Permission::THERAPY_SESSIONS_WRITE));
        $this->assertFalse($therapist->can(Permission::TRAINING_RECORDS_WRITE));
        $this->assertFalse($therapist->can(Permission::TRAINING_ATTENDANCE_MARK));
    }

    public function test_parent_only_has_portal_access(): void
    {
        $parent = $this->userWithRole(Role::Parent);

        $this->assertSame([Permission::PORTAL_ACCESS], $parent->getAllPermissions()->pluck('name')->all());
    }

    public function test_accountant_has_accounts_but_no_clinical_access(): void
    {
        $accountant = $this->userWithRole(Role::Accountant);

        $this->assertTrue($accountant->can(Permission::ACCOUNTS_VOUCHER_CREATE));
        $this->assertTrue($accountant->can(Permission::REPORTS_FINANCIAL));
        $this->assertFalse($accountant->can(Permission::PATIENTS_VIEW_CLINICAL));
        $this->assertFalse($accountant->can(Permission::THERAPY_SESSIONS_VIEW));
    }

    public function test_super_admin_passes_every_check(): void
    {
        $this->assertTrue($this->userWithRole(Role::SuperAdmin)->can(Permission::ACCOUNTS_PERIOD_CLOSE));
    }

    public function test_roles_endpoint_lists_all_seven_roles(): void
    {
        $this->actingAs($this->userWithRole(Role::BranchAdmin))
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(7, 'data');
    }

    public function test_only_super_admin_can_change_role_permissions(): void
    {
        $payload = ['permissions' => [Permission::PATIENTS_VIEW]];

        $this->actingAs($this->userWithRole(Role::BranchAdmin))
            ->putJson('/api/v1/roles/receptionist', $payload)
            ->assertForbidden();

        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->putJson('/api/v1/roles/receptionist', $payload)
            ->assertOk();

        $this->assertSame([Permission::PATIENTS_VIEW], $this->userWithRole(Role::Receptionist)->getAllPermissions()->pluck('name')->all());
    }

    public function test_super_admin_role_cannot_be_edited(): void
    {
        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->putJson('/api/v1/roles/super_admin', ['permissions' => []])
            ->assertUnprocessable();
    }
}
