<?php

namespace Tests\Feature\Enrollments;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class EnrollmentRulesTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private Service $speech;

    private Service $ot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech']);
        $this->ot = Service::factory()->create(['name' => 'Occupational Therapy', 'slug' => 'ot']);
    }

    private function receptionist()
    {
        return $this->userWithRole(Role::Receptionist, $this->branch);
    }

    private function patient(): Patient
    {
        return Patient::factory()->create(['home_branch_id' => $this->branch->id]);
    }

    /** Plan §৩: Ayan (training + speech + OT), Sara (speech only), Rafi (training only). */
    public function test_the_plan_example_ayan_sara_rafi(): void
    {
        $class = $this->classFor($this->branch);
        $imran = $this->therapistFor($this->speech);
        $farhana = $this->therapistFor($this->ot);
        $user = $this->receptionist();
        [$ayan, $sara, $rafi] = [$this->patient(), $this->patient(), $this->patient()];

        foreach ([
            [$ayan, ['type' => 'training', 'training_group_id' => $class->id]],
            [$ayan, ['type' => 'therapy', 'service_id' => $this->speech->id, 'therapist_id' => $imran->id]],
            [$ayan, ['type' => 'therapy', 'service_id' => $this->ot->id, 'therapist_id' => $farhana->id]],
            [$sara, ['type' => 'therapy', 'service_id' => $this->speech->id, 'therapist_id' => $imran->id]],
            [$rafi, ['type' => 'training', 'training_group_id' => $class->id]],
        ] as [$patient, $payload]) {
            $this->actingAs($user)->postJson('/api/v1/enrollments', [
                'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'start_date' => today()->toDateString(), ...$payload,
            ])->assertCreated();
        }

        $this->assertSame('both', $this->getJson("/api/v1/patients/{$ayan->id}")->json('data.type'));
        $this->assertSame('therapy', $this->getJson("/api/v1/patients/{$sara->id}")->json('data.type'));
        $this->assertSame('student', $this->getJson("/api/v1/patients/{$rafi->id}")->json('data.type'));
        $this->assertCount(3, $this->getJson("/api/v1/patients/{$ayan->id}")->json('data.enrollments'));

        $students = collect($this->getJson('/api/v1/patients?type=student')->json('data'))->pluck('id');
        $this->assertEquals([$rafi->id], $students->all());
    }

    public function test_training_requires_a_class_and_rejects_therapist_fields(): void
    {
        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'start_date' => today()->toDateString(), 'therapist_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['training_group_id', 'therapist_id']);
    }

    public function test_therapy_requires_service_and_therapist_and_rejects_class_fields(): void
    {
        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'therapy',
            'start_date' => today()->toDateString(), 'training_group_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['service_id', 'therapist_id', 'training_group_id']);
    }

    public function test_training_defaults_to_the_class_lead_trainer(): void
    {
        $lead = $this->trainerFor($this->branch);
        $class = $this->classFor($this->branch, $lead);

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'training_group_id' => $class->id, 'start_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.training.trainer.id', $lead->id);
    }

    public function test_a_child_can_have_only_one_open_training_enrollment(): void
    {
        $patient = $this->patient();
        $this->enrollTraining($patient, $this->classFor($this->branch));

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'training_group_id' => $this->classFor($this->branch)->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_same_therapy_twice_is_blocked_but_another_therapy_is_allowed(): void
    {
        $patient = $this->patient();
        $imran = $this->therapistFor($this->speech);
        $this->enrollTherapy($patient, $this->speech, $imran);
        $user = $this->receptionist();

        $this->actingAs($user)->postJson('/api/v1/enrollments', [
            'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'type' => 'therapy',
            'service_id' => $this->speech->id, 'therapist_id' => $imran->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');

        $this->actingAs($user)->postJson('/api/v1/enrollments', [
            'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'type' => 'therapy',
            'service_id' => $this->ot->id, 'therapist_id' => $this->therapistFor($this->ot)->id, 'start_date' => today()->toDateString(),
        ])->assertCreated();
    }

    public function test_therapist_must_provide_the_chosen_service(): void
    {
        $speechOnly = $this->therapistFor($this->speech);

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'therapy',
            'service_id' => $this->ot->id, 'therapist_id' => $speechOnly->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('therapist_id');
    }

    public function test_full_class_is_rejected(): void
    {
        $class = $this->classFor($this->branch, max: 1);
        $this->enrollTraining($this->patient(), $class);

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'training_group_id' => $class->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('training_group_id');
    }

    public function test_class_from_another_branch_is_rejected(): void
    {
        $otherClass = $this->classFor(Branch::factory()->create());

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'training_group_id' => $otherClass->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('training_group_id');
    }

    public function test_cannot_enroll_into_a_branch_you_do_not_belong_to(): void
    {
        $other = Branch::factory()->create();

        $this->actingAs($this->receptionist())->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $other->id, 'type' => 'training',
            'training_group_id' => $this->classFor($other)->id, 'start_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_trainer_cannot_create_enrollments(): void
    {
        $class = $this->classFor($this->branch);

        $this->actingAs($this->userWithRole(Role::Trainer, $this->branch))->postJson('/api/v1/enrollments', [
            'patient_id' => $this->patient()->id, 'branch_id' => $this->branch->id, 'type' => 'training',
            'training_group_id' => $class->id, 'start_date' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_status_lifecycle(): void
    {
        $enrollment = $this->enrollTraining($this->patient(), $this->classFor($this->branch));
        $user = $this->receptionist();

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/hold")->assertOk()->assertJsonPath('data.status', 'on_hold');
        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/hold")->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/resume")->assertOk()->assertJsonPath('data.status', 'active');

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/discontinue")
            ->assertUnprocessable()->assertJsonValidationErrors('end_reason');

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/discontinue", ['end_reason' => 'relocated'])
            ->assertOk()
            ->assertJsonPath('data.status', 'discontinued')
            ->assertJsonPath('data.end_date', today()->toDateString())
            ->assertJsonPath('data.assignments.0.to_date', today()->toDateString());

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/resume")->assertUnprocessable();
    }

    public function test_after_completion_the_child_is_no_longer_a_student_but_still_a_patient(): void
    {
        $patient = $this->patient();
        $enrollment = $this->enrollTraining($patient, $this->classFor($this->branch));
        $user = $this->receptionist();

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/complete")->assertOk();

        $this->actingAs($user)->getJson("/api/v1/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'none')
            ->assertJsonCount(1, 'data.enrollments');
    }

    public function test_transfer_to_another_class_keeps_assignment_history(): void
    {
        $from = $this->classFor($this->branch);
        $newLead = $this->trainerFor($this->branch);
        $to = $this->classFor($this->branch, $newLead);
        $enrollment = $this->enrollTraining($this->patient(), $from);

        $this->actingAs($this->receptionist())
            ->postJson("/api/v1/enrollments/{$enrollment->id}/transfer", ['training_group_id' => $to->id, 'reason' => 'Better age group'])
            ->assertOk()
            ->assertJsonPath('data.training.class.id', $to->id)
            ->assertJsonPath('data.training.trainer.id', $newLead->id)
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonPath('data.assignments.0.to_date', null)
            ->assertJsonPath('data.assignments.1.to_date', today()->toDateString());
    }

    public function test_therapy_transfer_requires_a_therapist_providing_the_service(): void
    {
        $enrollment = $this->enrollTherapy($this->patient(), $this->speech, $this->therapistFor($this->speech));
        $user = $this->receptionist();

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/transfer", [
            'therapist_id' => $this->therapistFor($this->ot)->id, 'reason' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors('therapist_id');

        $this->actingAs($user)->postJson("/api/v1/enrollments/{$enrollment->id}/transfer", [
            'therapist_id' => $this->therapistFor($this->speech)->id, 'reason' => 'Therapist on leave',
        ])->assertOk()->assertJsonCount(2, 'data.assignments');
    }
}
