<?php

namespace Tests\Feature\Patients;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardianAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->branch = Branch::factory()->create();
        $this->patient = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->patient->guardians()->attach(Guardian::factory()->create(['phone' => '01811111111']), ['relationship' => 'mother', 'is_primary' => true]);
    }

    public function test_add_second_guardian_and_switch_primary(): void
    {
        $user = $this->userWithRole(Role::Receptionist, $this->branch);

        $this->actingAs($user)->postJson("/api/v1/patients/{$this->patient->id}/guardians", [
            'name' => 'Karim', 'phone' => '01822222222', 'relationship' => 'father', 'is_primary' => true,
        ])->assertOk()->assertJsonCount(2, 'data');

        $primary = $this->patient->guardians()->wherePivot('is_primary', true)->get();
        $this->assertCount(1, $primary);
        $this->assertSame('Karim', $primary->first()->name);
    }

    public function test_last_guardian_cannot_be_removed(): void
    {
        $guardian = $this->patient->guardians()->first();

        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))
            ->deleteJson("/api/v1/patients/{$this->patient->id}/guardians/{$guardian->id}")
            ->assertUnprocessable();
    }

    public function test_reception_creates_parent_portal_account(): void
    {
        $guardian = $this->patient->guardians()->first();

        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))
            ->postJson("/api/v1/guardians/{$guardian->id}/portal-account", ['password' => 'Parent123'])
            ->assertCreated()
            ->assertJsonPath('data.login', '01811111111');

        $user = User::where('phone', '01811111111')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Parent->value));
        $this->assertTrue($user->must_change_password);
        $this->assertSame($user->id, $guardian->fresh()->user_id);
        $this->assertSame('/portal', $user->homePath());
    }

    public function test_documents_are_private_and_clinical_ones_need_clinical_access(): void
    {
        $receptionist = $this->userWithRole(Role::Receptionist, $this->branch);

        $report = $this->actingAs($receptionist)->post("/api/v1/patients/{$this->patient->id}/documents", [
            'file' => UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
            'category' => 'medical_report', 'title' => 'Neurologist report',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->actingAs($receptionist)->post("/api/v1/patients/{$this->patient->id}/documents", [
            'file' => UploadedFile::fake()->image('birth.jpg'),
            'category' => 'id_document', 'title' => 'Birth certificate',
        ], ['Accept' => 'application/json'])->assertCreated();

        // Stored on the private disk, not under public/
        $this->assertCount(2, Storage::disk('local')->allFiles("patients/{$this->patient->id}/documents"));

        // Reception (no clinical access) sees only the non-clinical one and cannot download the report
        $this->actingAs($receptionist)->getJson("/api/v1/patients/{$this->patient->id}/documents")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'id_document');
        $this->actingAs($receptionist)->get("/api/v1/documents/{$report['id']}/download")->assertForbidden();

        // Branch admin (clinical access) can download it, and the access is audited
        $this->actingAs($this->userWithRole(Role::BranchAdmin, $this->branch))
            ->get("/api/v1/documents/{$report['id']}/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'viewed', 'auditable_id' => $report['id']]);

        // Staff of another branch cannot download at all
        $this->actingAs($this->userWithRole(Role::BranchAdmin))
            ->get("/api/v1/documents/{$report['id']}/download")->assertForbidden();
    }

    public function test_photo_upload_and_authorised_fetch(): void
    {
        $receptionist = $this->userWithRole(Role::Receptionist, $this->branch);

        $this->actingAs($receptionist)->post("/api/v1/patients/{$this->patient->id}/photo", [
            'photo' => UploadedFile::fake()->image('ayan.jpg', 300, 300),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($receptionist)->get("/api/v1/patients/{$this->patient->id}/photo")->assertOk();
        $this->actingAs($this->userWithRole(Role::Receptionist))->get("/api/v1/patients/{$this->patient->id}/photo")->assertForbidden();
    }

    public function test_dangerous_file_types_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))
            ->post("/api/v1/patients/{$this->patient->id}/documents", [
                'file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php'),
                'category' => 'other', 'title' => 'x',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
