<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_history_counts_sign_ins_and_flags_repeated_failures(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $staff = $this->userWithRole(Role::Receptionist);

        for ($i = 0; $i < 5; $i++) {
            AuditLogger::log('login_failed', $staff, new: ['login' => $staff->email]);
        }
        AuditLogger::log('login', $staff, userId: $staff->id);

        $res = $this->actingAs($admin)->getJson('/api/v1/audit-logs?type=login')->assertOk();
        $this->assertSame(1, $res->json('summary.logins'));
        $this->assertSame(5, $res->json('summary.failed'));
        $this->assertSame(5, $res->json('summary.suspicious_ips.0.attempts'));
        $this->assertSame(6, $res->json('meta.total'));
    }

    public function test_patient_activity_collects_records_of_one_child(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $this->actingAs($admin);
        $child = Patient::factory()->create();
        $other = Patient::factory()->create();
        $child->update(['name' => 'Renamed Child']);
        AuditLogger::log('viewed', $child, new: ['section' => 'clinical']);

        $rows = $this->getJson("/api/v1/audit-logs?type=patient&patient_id={$child->id}")->assertOk()->json('data');
        $this->assertSame(['viewed', 'updated', 'created'], array_column($rows, 'action'));
        $this->assertSame('Renamed Child', $rows[1]['changes'][0]['new']);
        $this->assertNotContains($other->id, array_column(array_column($rows, 'patient'), 'id'));

        // Clinical access lists only "viewed"; system activity leaves views and logins out.
        $this->assertSame(['viewed'], array_column($this->getJson('/api/v1/audit-logs?type=clinical')->json('data'), 'action'));
        $this->assertNotContains('viewed', array_column($this->getJson('/api/v1/audit-logs?type=system')->json('data'), 'action'));
    }

    public function test_branch_admin_sees_only_own_branch_and_receptionist_none(): void
    {
        [$a, $b] = Branch::factory()->count(2)->create();
        $adminA = $this->userWithRole(Role::BranchAdmin, $a);
        $staffA = $this->userWithRole(Role::Receptionist, $a);
        $staffB = $this->userWithRole(Role::Receptionist, $b);

        $this->actingAs($staffA);
        $mine = Patient::factory()->create(['home_branch_id' => $a->id]);
        $this->actingAs($staffB);
        $theirs = Patient::factory()->create(['home_branch_id' => $b->id]);

        $ids = array_column(array_filter($this->actingAs($adminA)->getJson('/api/v1/audit-logs?per_page=100')->assertOk()->json('data'), fn ($r) => $r['patient']), 'patient');
        $this->assertContains($mine->id, array_column($ids, 'id'));
        $this->assertNotContains($theirs->id, array_column($ids, 'id'));

        $this->actingAs($staffA)->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_csv_export_is_itself_logged(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);
        AuditLogger::log('login', $admin, userId: $admin->id);

        $res = $this->actingAs($admin)->get('/api/v1/audit-logs?type=login&format=csv')->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('login', $res->streamedContent());
        $this->assertTrue(AuditLog::where('action', 'exported')->where('user_id', $admin->id)->exists());
    }

    public function test_password_change_is_recorded(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole(Role::Receptionist->value);

        $this->actingAs($user)->putJson('/api/v1/auth/password', [
            'current_password' => 'password', 'password' => 'NewPass@2026', 'password_confirmation' => 'NewPass@2026',
        ])->assertOk();

        $this->assertTrue(AuditLog::where('action', 'password_changed')->where('user_id', $user->id)->exists());
    }
}
