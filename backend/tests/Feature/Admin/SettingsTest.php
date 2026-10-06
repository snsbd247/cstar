<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use App\Services\BackupService;
use App\Services\PatientService;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(Role::SuperAdmin);
    }

    public function test_patient_id_format_follows_settings_and_never_repeats(): void
    {
        $branch = Branch::factory()->create(['code' => 'MIR']);
        $this->actingAs($this->admin)->putJson('/api/v1/settings/patient_id', [
            'prefix' => 'CS', 'digits' => 4, 'include_year' => '0', 'include_branch_code' => '1',
        ])->assertOk()->assertJsonPath('data.groups.patient_id.prefix', 'CS');

        $ids = app(PatientService::class);
        $this->assertSame('CS-MIR-0001', $ids->nextCode($branch->id));
        // A code already in use (e.g. typed in by an earlier format) is skipped, not duplicated.
        Patient::factory()->create(['patient_code' => 'CS-MIR-0002', 'home_branch_id' => $branch->id]);
        $this->assertSame('CS-MIR-0003', $ids->nextCode($branch->id));

        $this->putJson('/api/v1/settings/patient_id', ['prefix' => 'cs-1', 'digits' => 2, 'include_year' => '1', 'include_branch_code' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors(['prefix', 'digits']);
        $this->assertTrue(AuditLog::where('action', 'settings.updated')->exists());
    }

    public function test_only_super_admin_reaches_settings(): void
    {
        $branchAdmin = $this->userWithRole(Role::BranchAdmin);
        $this->actingAs($branchAdmin)->getJson('/api/v1/settings')->assertForbidden();
        $this->actingAs($branchAdmin)->putJson('/api/v1/settings/security', [])->assertForbidden();
        $this->actingAs($this->admin)->putJson('/api/v1/settings/unknown', [])->assertNotFound();
    }

    public function test_security_settings_change_password_rules_and_login_limit(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/settings/security', [
            'session_timeout_minutes' => 30, 'password_min_length' => 12, 'password_require_symbol' => '1', 'login_attempts_per_minute' => 3,
        ])->assertOk();

        $user = User::factory()->create();
        $user->assignRole(Role::Receptionist->value);
        $user = $user->fresh();
        $this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'Short1pass', 'password_confirmation' => 'Short1pass'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'LongEnough12!x', 'password_confirmation' => 'LongEnough12!x'])
            ->assertOk();

        // Session lifetime comes from the setting on every request.
        $this->getJson('/api/v1/auth/me')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(30, config('session.lifetime'));

        auth()->guard('web')->logout();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'nobody@example.com', 'password' => 'wrong'], $this->spaHeaders())->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['login' => 'nobody@example.com', 'password' => 'wrong'], $this->spaHeaders())->assertStatus(429);
    }

    public function test_appointment_and_pdf_settings_are_used(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/settings/appointment', ['late_cancel_hours' => 6, 'recurring_weeks' => 2, 'portal_requests_enabled' => '0'])->assertOk();
        $this->assertSame(6, Appointment::lateCancelHours());

        $this->putJson('/api/v1/settings/center', ['legal_name' => 'C-STAR Foundation', 'phone' => '01700000000', 'registration_no' => 'DSS-123'])->assertOk();
        $this->putJson('/api/v1/settings/pdf', ['paper_size' => 'Letter', 'brand_color' => '#123456', 'footer_note' => 'Private', 'show_printed_date' => '0'])->assertOk();
        $head = app(PdfService::class)->letterhead();
        $this->assertSame('C-STAR Foundation', $head['full_name']);
        $this->assertSame('#123456', $head['color']);
        $this->assertSame('DSS-123', $head['registration_no']);
        $this->assertStringStartsWith('%PDF', app(PdfService::class)->render('pdf.report', ['report' => ['title' => 'T', 'columns' => [], 'rows' => [], 'totals' => null, 'from' => '2026-01-01', 'to' => '2026-01-31']], 'Test'));
    }

    public function test_backup_create_download_and_delete(): void
    {
        $this->actingAs($this->admin);
        $name = $this->postJson('/api/v1/settings/backups')->assertCreated()->json('data.name');
        $this->assertMatchesRegularExpression('/^cstar-db-\d{8}-\d{6}\.sql\.gz$/', $name);
        $this->assertSame($name, $this->getJson('/api/v1/settings/backups')->json('data.0.name'));

        $sql = gzdecode(file_get_contents(app(BackupService::class)->path($name)));
        $this->assertStringContainsString('CREATE TABLE `patients`', $sql);

        $this->get("/api/v1/settings/backups/{$name}")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'backup.downloaded')->exists());
        $this->getJson('/api/v1/settings/backups/..%2F.env')->assertStatus(404);
        $this->getJson('/api/v1/settings/backups/cstar-db-20200101-000000.sql.gz')->assertUnprocessable();

        $this->deleteJson("/api/v1/settings/backups/{$name}")->assertOk();
        $this->assertNotContains($name, array_column($this->getJson('/api/v1/settings/backups')->json('data'), 'name'));

        $checks = $this->getJson('/api/v1/settings/system')->assertOk()->json('data.checks');
        $this->assertNotEmpty($checks);
    }
}
