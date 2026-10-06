<?php

namespace Tests\Feature\Patients;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Diagnosis;
use App\Models\Guardian;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Branch $branch, array $overrides = []): array
    {
        return [
            'home_branch_id' => $branch->id,
            'name' => 'Ayan Rahman',
            'date_of_birth' => '2021-03-14',
            'gender' => 'male',
            'phone' => '01712345678',
            'clinical' => ['medical_history' => 'Febrile seizure at 2y'],
            'diagnosis_ids' => [Diagnosis::firstOrCreate(['name' => 'ASD'])->id],
            'guardian' => ['name' => 'Nusrat Jahan', 'phone' => '01712345678', 'relationship' => 'mother'],
            'consents' => ['treatment' => true, 'photo_media' => false],
            ...$overrides,
        ];
    }

    public function test_receptionist_registers_a_child_with_guardian_clinical_intake_and_consents(): void
    {
        $branch = Branch::factory()->create();
        $receptionist = $this->userWithRole(Role::Receptionist, $branch);

        $response = $this->actingAs($receptionist)->postJson('/api/v1/patients', $this->payload($branch))->assertCreated();

        $year = now()->year;
        $response->assertJsonPath('data.patient_code', "CSTAR-{$year}-00001")
            ->assertJsonPath('data.type', 'none')
            ->assertJsonPath('data.guardians.0.relationship', 'mother')
            ->assertJsonPath('data.guardians.0.is_primary', true);

        $patient = Patient::firstOrFail();
        $this->assertSame('Febrile seizure at 2y', $patient->clinicalProfile->medical_history); // written at intake…
        $this->assertArrayNotHasKey('clinical_profile', $response->json('data'));              // …but not readable by reception
        $this->assertEquals(['treatment' => true, 'photo_media' => false], $patient->consents->pluck('granted', 'type')->all());
        $this->assertDatabaseHas('timeline_events', ['patient_id' => $patient->id, 'event_type' => 'patient.registered']);

        $this->actingAs($receptionist)->postJson('/api/v1/patients', $this->payload($branch, ['name' => 'Second']))
            ->assertJsonPath('data.patient_code', "CSTAR-{$year}-00002");
    }

    public function test_guardian_is_required_and_phone_must_be_bangladeshi(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->userWithRole(Role::Receptionist, $branch))
            ->postJson('/api/v1/patients', $this->payload($branch, ['guardian' => null, 'phone' => '12345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guardian', 'phone']);
    }

    public function test_cannot_register_into_another_branch(): void
    {
        $this->actingAs($this->userWithRole(Role::Receptionist))
            ->postJson('/api/v1/patients', $this->payload(Branch::factory()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('home_branch_id');
    }

    public function test_sibling_reuses_existing_guardian(): void
    {
        $branch = Branch::factory()->create();
        $receptionist = $this->userWithRole(Role::Receptionist, $branch);
        $mother = Guardian::factory()->create(['phone' => '01799999999']);

        $this->actingAs($receptionist)->getJson('/api/v1/guardians/lookup?phone=01799999999')
            ->assertOk()->assertJsonPath('data.0.id', $mother->id);

        $this->actingAs($receptionist)
            ->postJson('/api/v1/patients', $this->payload($branch, ['guardian' => ['id' => $mother->id, 'relationship' => 'mother']]))
            ->assertCreated();

        $this->assertSame(1, Guardian::count());
        $this->assertSame(1, $mother->patients()->count());
    }

    public function test_duplicate_check_finds_same_phone_and_birth_date(): void
    {
        $branch = Branch::factory()->create();
        $existing = Patient::factory()->create(['phone' => '01712345678', 'date_of_birth' => '2021-03-14']);

        $this->actingAs($this->userWithRole(Role::Receptionist, $branch))
            ->getJson('/api/v1/patients/check-duplicates?phone=01712345678&date_of_birth=2021-03-14')
            ->assertOk()
            ->assertJsonPath('data.0.patient_code', $existing->patient_code);
    }

    public function test_receptionist_update_cannot_overwrite_clinical_data(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $patient->clinicalProfile()->create(['medical_history' => 'Original']);

        $this->actingAs($this->userWithRole(Role::Receptionist, $branch))
            ->putJson("/api/v1/patients/{$patient->id}", [
                'home_branch_id' => $branch->id, 'name' => 'Renamed', 'date_of_birth' => '2020-01-01',
                'gender' => 'male', 'phone' => '01712345678', 'clinical' => ['medical_history' => 'Tampered'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Original', $patient->clinicalProfile->fresh()->medical_history);
    }

    public function test_search_by_code_name_or_guardian_phone(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['home_branch_id' => $branch->id, 'name' => 'Nabil Hasan']);
        $patient->guardians()->attach(Guardian::factory()->create(['phone' => '01855555555']), ['relationship' => 'father', 'is_primary' => true]);
        Patient::factory()->create(['home_branch_id' => $branch->id, 'name' => 'Someone Else']);
        $user = $this->userWithRole(Role::Receptionist, $branch);

        foreach (['Nabil', $patient->patient_code, '01855555555'] as $term) {
            $this->actingAs($user)->getJson('/api/v1/patients?search='.urlencode($term))
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        }
    }
}
