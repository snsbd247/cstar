<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Consent;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Service;
use App\Services\TimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class PatientRecordsTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
    }

    private function child(array $attrs = []): Patient
    {
        return Patient::factory()->create(['home_branch_id' => $this->branch->id, ...$attrs]);
    }

    public function test_enrollment_list_filters_counts_and_transfer_history(): void
    {
        $from = $this->classFor($this->branch);
        $to = $this->classFor($this->branch, $this->trainerFor($this->branch));
        $speech = Service::factory()->create();
        $ayan = $this->child(['name' => 'Ayan Rahman']);
        $sara = $this->child(['name' => 'Sara Khan']);
        $training = $this->enrollTraining($ayan, $from);
        $this->enrollTherapy($sara, $speech, $this->therapistFor($speech));
        $user = $this->userWithRole(Role::Receptionist, $this->branch);

        $res = $this->actingAs($user)->getJson('/api/v1/enrollments?type=training')->assertOk();
        $this->assertSame([$training->id], array_column($res->json('data'), 'id'));
        $this->assertSame(1, $res->json('counts.active'));
        $this->assertSame(['Sara Khan'], array_column(array_column($this->getJson('/api/v1/enrollments?q=sara')->json('data'), 'patient'), 'name'));

        $this->postJson("/api/v1/enrollments/{$training->id}/transfer", ['training_group_id' => $to->id, 'reason' => 'Better age group'])->assertOk();
        $rows = $this->getJson('/api/v1/enrollment-transfers')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString($from->name, $rows[0]['from']);
        $this->assertStringContainsString($to->name, $rows[0]['to']);
        $this->assertSame('Better age group', $rows[0]['reason']);

        // Another branch's receptionist sees neither the enrollments nor the transfer.
        $other = $this->userWithRole(Role::Receptionist, Branch::factory()->create());
        $this->actingAs($other)->getJson('/api/v1/enrollment-transfers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/enrollments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guardians_documents_consents_and_timeline_lists(): void
    {
        $ayan = $this->child(['name' => 'Ayan Rahman']);
        $sara = $this->child(['name' => 'Sara Khan']);
        $mother = Guardian::factory()->create(['name' => 'Nasrin Rahman']);
        $ayan->guardians()->attach($mother->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);
        Consent::create(['patient_id' => $ayan->id, 'guardian_id' => $mother->id, 'type' => 'treatment', 'granted' => true, 'signed_on' => today()]);
        PatientDocument::create(['patient_id' => $ayan->id, 'category' => 'medical_report', 'title' => 'Hospital report', 'path' => 'x', 'original_name' => 'r.pdf', 'mime' => 'application/pdf', 'size' => 1000]);
        PatientDocument::create(['patient_id' => $ayan->id, 'category' => 'id_document', 'title' => 'Birth certificate', 'path' => 'y', 'original_name' => 'b.pdf', 'mime' => 'application/pdf', 'size' => 1000]);
        app(TimelineService::class)->record($ayan, 'patient.registered', 'Registered', $ayan);
        app(TimelineService::class)->record($sara, 'patient.registered', 'Registered', $sara);

        $reception = $this->userWithRole(Role::Receptionist, $this->branch);
        $this->actingAs($reception);

        $guardians = $this->getJson('/api/v1/records/guardians?q=ayan')->assertOk()->json('data');
        $this->assertSame('Nasrin Rahman', $guardians[0]['name']);
        $this->assertSame('mother', $guardians[0]['children'][0]['relationship']);

        // Receptionists do not see clinical documents (medical reports); a branch admin does.
        $this->assertSame(['Birth certificate'], array_column($this->getJson('/api/v1/records/documents')->json('data'), 'title'));
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->assertCount(2, $this->actingAs($admin)->getJson('/api/v1/records/documents')->json('data'));

        $this->actingAs($reception);
        $consents = $this->getJson('/api/v1/records/consents')->assertOk();
        $this->assertSame(1, $consents->json('missing'));
        $this->assertSame('Treatment', $consents->json('data.0.type_label'));
        $this->assertSame(['Sara Khan'], array_column(array_column($this->getJson('/api/v1/records/consents?view=missing')->json('data'), 'patient'), 'name'));

        $this->assertCount(2, $this->getJson('/api/v1/records/timeline')->json('data'));
        $this->assertSame([$ayan->id], array_column(array_column($this->getJson("/api/v1/records/timeline?patient_id={$ayan->id}")->json('data'), 'patient'), 'id'));

        $stranger = $this->userWithRole(Role::Receptionist, Branch::factory()->create());
        $this->actingAs($stranger)->getJson('/api/v1/records/guardians')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/records/timeline')->assertOk()->assertJsonCount(0, 'data');
    }
}
