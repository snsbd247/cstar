<?php

namespace Tests\Feature\Patients;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Plan §৩৫: who may see which child. */
class PatientAccessTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_receptionist_only_sees_children_of_own_branch(): void
    {
        $own = Branch::factory()->create();
        $mine = Patient::factory()->create(['home_branch_id' => $own->id]);
        $theirs = Patient::factory()->create();
        $receptionist = $this->userWithRole(Role::Receptionist, $own);

        $ids = collect($this->actingAs($receptionist)->getJson('/api/v1/patients')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$mine->id], $ids->all());

        $this->actingAs($receptionist)->getJson("/api/v1/patients/{$theirs->id}")->assertForbidden();
    }

    public function test_child_enrolled_in_my_branch_is_visible_even_if_registered_elsewhere(): void
    {
        $own = Branch::factory()->create();
        $patient = Patient::factory()->create(); // other home branch
        $this->enrollTraining($patient, $this->classFor($own));

        $this->actingAs($this->userWithRole(Role::Receptionist, $own))
            ->getJson("/api/v1/patients/{$patient->id}")->assertOk();
    }

    public function test_trainer_sees_only_assigned_students_and_loses_access_when_enrollment_ends(): void
    {
        $branch = Branch::factory()->create();
        $trainerUser = $this->userWithRole(Role::Trainer, $branch);
        $class = $this->classFor($branch, $this->trainerFor($branch, $trainerUser));
        $student = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $enrollment = $this->enrollTraining($student, $class);

        $therapyOnly = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $speech = Service::factory()->create();
        $this->enrollTherapy($therapyOnly, $speech, $this->therapistFor($speech), $branch);

        $ids = collect($this->actingAs($trainerUser)->getJson('/api/v1/patients')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$student->id], $ids->all());
        $this->actingAs($trainerUser)->getJson("/api/v1/patients/{$therapyOnly->id}")->assertForbidden();

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))
            ->postJson("/api/v1/enrollments/{$enrollment->id}/complete")->assertOk();

        $this->actingAs($trainerUser->fresh())->getJson("/api/v1/patients/{$student->id}")->assertForbidden();
    }

    public function test_therapist_sees_only_own_therapy_patients(): void
    {
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create();
        $therapistUser = $this->userWithRole(Role::Therapist, $branch);
        $me = $this->therapistFor($speech, $therapistUser);

        $mine = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $this->enrollTherapy($mine, $speech, $me, $branch);
        $colleagues = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $this->enrollTherapy($colleagues, $speech, $this->therapistFor($speech), $branch);
        $studentOnly = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $this->enrollTraining($studentOnly, $this->classFor($branch));

        $ids = collect($this->actingAs($therapistUser)->getJson('/api/v1/patients')->json('data'))->pluck('id');
        $this->assertEquals([$mine->id], $ids->all());

        $this->actingAs($therapistUser)->getJson("/api/v1/patients/{$mine->id}")
            ->assertOk()->assertJsonStructure(['data' => ['clinical_profile', 'diagnoses']]);
    }

    public function test_receptionist_and_accountant_never_receive_clinical_data(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $patient->clinicalProfile()->create(['medical_history' => 'Epilepsy']);

        foreach ([Role::Receptionist, Role::Accountant] as $role) {
            $response = $this->actingAs($this->userWithRole($role, $branch))->getJson("/api/v1/patients/{$patient->id}")->assertOk();
            $this->assertArrayNotHasKey('clinical_profile', $response->json('data'));
            $this->assertFalse($response->json('data.can.view_clinical'));
        }

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))
            ->getJson("/api/v1/patients/{$patient->id}")
            ->assertJsonPath('data.clinical_profile.medical_history', 'Epilepsy');

        $this->assertDatabaseHas('audit_logs', ['action' => 'viewed', 'auditable_id' => $patient->id, 'auditable_type' => Patient::class]);
    }

    public function test_parent_cannot_use_the_staff_patient_api(): void
    {
        $this->actingAs($this->userWithRole(Role::Parent))->getJson('/api/v1/patients')->assertForbidden();
    }
}
