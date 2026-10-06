<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TherapySession;
use App\Models\TrainingGroupSchedule;
use Database\Seeders\AssessmentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Training, Therapy and Assessments menu pages added in Sprint 16. */
class ProgrammePagesTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
    }

    public function test_training_pages_show_todays_classes_schedule_and_plans(): void
    {
        $class = $this->classFor($this->branch);
        TrainingGroupSchedule::create(['training_group_id' => $class->id, 'weekday' => today()->dayOfWeek, 'start_time' => '10:00', 'end_time' => '12:00']);
        $child = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $enrollment = $this->enrollTraining($child, $class);
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->actingAs($admin);

        $dash = $this->getJson('/api/v1/training/dashboard')->assertOk();
        $this->assertSame(1, $dash->json('data.kpis.active_students'));
        $this->assertSame($class->id, $dash->json('data.today.0.id'));
        $this->assertSame(1, $dash->json('data.today.0.students'));
        $this->assertSame('10:00', $this->getJson('/api/v1/training/schedule')->json('data.0.days.0.start'));
        $this->assertSame(0, $this->getJson('/api/v1/training/attendance-day')->json('data.classes.0.marked'));
        $this->getJson('/api/v1/training/sessions')->assertOk();

        $this->postJson("/api/v1/enrollments/{$enrollment->id}/plans", [
            'title' => 'Term 1 plan', 'start_date' => today()->toDateString(), 'review_date' => today()->addDays(3)->toDateString(),
            'goals' => [['domain' => 'Motor', 'title' => 'Jump with both feet', 'progress_percent' => 40]],
        ])->assertSuccessful();
        $plans = $this->getJson('/api/v1/plans?type=training&review=due')->assertOk()->json('data');
        $this->assertSame('Term 1 plan', $plans[0]['title']);
        $this->assertTrue($plans[0]['review_due']);
        $this->assertSame(0, $this->getJson('/api/v1/plans?type=therapy')->json('meta.total'));

        // Activities: a branch admin manages the shared list; receptionists only read it.
        $id = $this->postJson('/api/v1/activity-types', ['name' => 'Ball games', 'applies_to' => 'training'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/activity-types/{$id}", ['name' => 'Ball games', 'applies_to' => 'training', 'is_active' => false])->assertOk();
        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))->postJson('/api/v1/activity-types', ['name' => 'X', 'applies_to' => 'both'])->assertForbidden();
    }

    public function test_therapy_pages_dashboard_schedule_home_programs_and_services(): void
    {
        $speech = Service::factory()->create(['name' => 'Speech Therapy']);
        $therapist = $this->therapistFor($speech, branch: $this->branch);
        $child = Patient::factory()->create(['home_branch_id' => $this->branch->id, 'name' => 'Sara Khan']);
        $this->enrollTherapy($child, $speech, $therapist, $this->branch);
        $appointment = Appointment::forceCreate([
            'appointment_code' => 'APT-T-1', 'patient_id' => $child->id, 'service_id' => $speech->id, 'therapist_id' => $therapist->id,
            'branch_id' => $this->branch->id, 'date' => today(), 'start_time' => '10:00', 'end_time' => '10:45', 'status' => 'completed',
        ]);
        TherapySession::forceCreate([
            'appointment_id' => $appointment->id, 'patient_id' => $child->id, 'therapist_id' => $therapist->id, 'service_id' => $speech->id, 'branch_id' => $this->branch->id,
            'date' => today(), 'status' => 'final', 'home_practice' => 'Name 5 pictures each evening', 'parent_summary' => 'Good session',
        ]);
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->actingAs($admin);

        $this->assertSame(1, $this->getJson('/api/v1/therapy/dashboard')->assertOk()->json('data.kpis.active_patients'));
        $this->getJson('/api/v1/therapy/schedule')->assertOk()->assertJsonCount(7, 'data.days');
        $this->assertSame('Name 5 pictures each evening', $this->getJson('/api/v1/therapy/home-programs?q=sara')->json('data.0.home_practice'));
        $this->assertSame(['Speech Therapy'], $this->getJson('/api/v1/therapy/progress-reports')->json('data.0.programmes'));

        $id = $this->postJson('/api/v1/service-catalog', ['name' => 'Feeding Therapy', 'category' => 'therapy', 'default_duration_min' => 45, 'default_price' => 1200])
            ->assertCreated()->json('data.id');
        $this->assertSame('feeding-therapy', Service::find($id)->slug);
        $this->putJson("/api/v1/service-catalog/{$id}", ['name' => 'Feeding Therapy', 'category' => 'therapy', 'default_duration_min' => 60, 'is_active' => false])->assertOk();
        $this->assertSame(1, collect($this->getJson('/api/v1/service-catalog')->json('data'))->firstWhere('name', 'Speech Therapy')['therapists']);
    }

    public function test_assessment_templates_keep_sections_that_hold_findings(): void
    {
        $this->seed(AssessmentTypeSeeder::class);
        $type = AssessmentType::where('name', 'Speech & Language Assessment')->firstOrFail();
        $speech = Service::factory()->create();
        $therapistUser = $this->userWithRole(Role::Therapist, $this->branch);
        $therapist = $this->therapistFor($speech, $therapistUser, $this->branch);
        $child = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->enrollTherapy($child, $speech, $therapist, $this->branch);
        $this->actingAs($therapistUser)->postJson("/api/v1/patients/{$child->id}/assessments", [
            'assessment_type_id' => $type->id, 'chief_complaint' => 'Limited speech', 'summary' => 'Delay', 'parent_summary' => 'Note',
            'section_findings' => ['expressive_language' => 'Single words only'],
            'recommendation_items' => [['enrollment_type' => 'therapy', 'service_id' => $speech->id, 'frequency' => '2 / week', 'priority' => 'high']],
        ])->assertCreated();

        $admin = $this->userWithRole(Role::SuperAdmin);
        $types = collect($this->actingAs($admin)->getJson('/api/v1/assessment-types')->assertOk()->json('data'));
        $sections = $types->firstWhere('id', $type->id)['sections'];
        $this->assertTrue(collect($sections)->firstWhere('key', 'expressive_language')['used']);

        // Removing the used section is refused; renaming it and adding a new one is fine.
        $without = collect($sections)->reject(fn ($s) => $s['key'] === 'expressive_language')->values()->all();
        $this->putJson("/api/v1/assessment-types/{$type->id}", ['name' => $type->name, 'sections' => $without])
            ->assertUnprocessable()->assertJsonValidationErrors('sections');
        $renamed = collect($sections)
            ->map(fn ($s) => $s['key'] === 'expressive_language' ? [...$s, 'label' => 'Expressive language (words & sentences)'] : $s)
            ->push(['key' => null, 'label' => 'Play skills'])->all();
        $this->putJson("/api/v1/assessment-types/{$type->id}", ['name' => $type->name, 'sections' => $renamed])->assertOk();
        $this->assertContains('play_skills', array_column($type->fresh()->sections, 'key'));

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $this->branch))
            ->putJson("/api/v1/assessment-types/{$type->id}", ['name' => 'x', 'sections' => $renamed])->assertForbidden();
    }
}
