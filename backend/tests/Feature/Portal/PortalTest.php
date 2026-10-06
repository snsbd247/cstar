<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Database\Seeders\AssessmentTypeSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $parent;

    private Patient $ayan;

    private Patient $sibling;

    private Patient $stranger;

    private Service $speech;

    private Therapist $imran;

    private Enrollment $therapy;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->seed([ChartOfAccountsSeeder::class, AssessmentTypeSeeder::class]);
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech']);
        $this->imran = $this->therapistFor($this->speech, null, $this->branch);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);

        $this->parent = $this->userWithRole(Role::Parent, $this->branch);
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id, 'name' => 'Nusrat Jahan']);
        $this->ayan = Patient::factory()->create(['home_branch_id' => $this->branch->id, 'name' => 'Ayan']);
        $this->sibling = Patient::factory()->create(['home_branch_id' => $this->branch->id, 'name' => 'Sibling']);
        $this->stranger = Patient::factory()->create(['home_branch_id' => $this->branch->id, 'name' => 'Stranger']);
        $guardian->patients()->attach($this->ayan, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);
        $guardian->patients()->attach($this->sibling, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => false]);

        $this->therapy = $this->enrollTherapy($this->ayan, $this->speech, $this->imran, $this->branch);
    }

    private function therapySession(string $status = 'final'): TherapySession
    {
        static $h = 8;
        $h++;
        $a = Appointment::create([
            'appointment_code' => 'APT-PT-'.uniqid(), 'patient_id' => $this->ayan->id, 'enrollment_id' => $this->therapy->id, 'service_id' => $this->speech->id,
            'therapist_id' => $this->imran->id, 'branch_id' => $this->branch->id, 'date' => today()->subDay()->toDateString(),
            'start_time' => sprintf('%02d:00:00', $h), 'end_time' => sprintf('%02d:45:00', $h), 'type' => 'therapy', 'status' => 'completed',
        ]);

        return TherapySession::create([
            'appointment_id' => $a->id, 'patient_id' => $this->ayan->id, 'therapist_id' => $this->imran->id, 'service_id' => $this->speech->id,
            'branch_id' => $this->branch->id, 'date' => $a->date, 'status' => $status,
            'parent_summary' => "Parent summary {$status}", 'therapist_notes' => 'SECRET clinical note', 'home_practice' => 'Name 5 objects',
        ]);
    }

    public function test_parent_sees_only_children_with_portal_access(): void
    {
        $this->actingAs($this->parent)->getJson('/api/v1/portal/children')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ayan')->assertJsonPath('data.0.has_therapy', true);

        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->sibling->id}/home")->assertNotFound();
        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->stranger->id}/billing")->assertNotFound();
        $this->actingAs($this->reception)->getJson('/api/v1/portal/children')->assertForbidden();
    }

    public function test_progress_shows_family_facing_notes_only(): void
    {
        $this->therapySession('final');
        $this->therapySession('draft');
        $type = AssessmentType::firstOrFail();
        Assessment::create(['assessment_code' => 'ASM-T-1', 'patient_id' => $this->ayan->id, 'assessment_type_id' => $type->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today(), 'status' => 'final', 'shared_with_parent' => true, 'parent_summary' => 'Shared report', 'summary' => 'Clinical']);
        $hidden = Assessment::create(['assessment_code' => 'ASM-T-2', 'patient_id' => $this->ayan->id, 'assessment_type_id' => $type->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today(), 'status' => 'final', 'shared_with_parent' => false, 'summary' => 'Not shared']);

        $res = $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->ayan->id}/progress")->assertOk();
        $res->assertJsonCount(1, 'data.notes')->assertJsonPath('data.notes.0.text', 'Parent summary final')->assertJsonPath('data.notes.0.home_practice', 'Name 5 objects')
            ->assertJsonCount(1, 'data.reports');
        $this->assertStringNotContainsString('SECRET', $res->getContent());
        $this->assertStringNotContainsString('Not shared', $res->getContent());

        $this->actingAs($this->parent)->get("/api/v1/portal/assessments/{$hidden->id}/pdf")->assertNotFound();
    }

    public function test_home_and_billing_show_due_and_documents(): void
    {
        $id = $this->actingAs($this->reception)->postJson('/api/v1/invoices', [
            'patient_id' => $this->ayan->id, 'branch_id' => $this->branch->id, 'issue' => true, 'items' => [['item_type' => 'admission', 'unit_price' => 2000]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->reception)->postJson('/api/v1/invoices', [
            'patient_id' => $this->ayan->id, 'branch_id' => $this->branch->id, 'items' => [['item_type' => 'other', 'unit_price' => 999]],
        ])->assertCreated(); // draft — invisible to the family
        $paymentId = $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->ayan->id}/payments", ['amount' => 500, 'method' => 'cash', 'branch_id' => $this->branch->id])->json('data.id');

        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->ayan->id}/home")->assertOk()->assertJsonPath('data.due', 1500);
        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->ayan->id}/billing")
            ->assertOk()->assertJsonCount(1, 'data.invoices')->assertJsonCount(1, 'data.payments');

        foreach (["/api/v1/portal/invoices/{$id}/pdf", "/api/v1/portal/payments/{$paymentId}/receipt", "/api/v1/portal/children/{$this->ayan->id}/progress-report"] as $url) {
            $pdf = $this->actingAs($this->parent)->get($url);
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }

        // Another family's invoice stays hidden.
        $otherInvoice = $this->actingAs($this->reception)->postJson('/api/v1/invoices', [
            'patient_id' => $this->stranger->id, 'branch_id' => $this->branch->id, 'issue' => true, 'items' => [['item_type' => 'other', 'unit_price' => 100]],
        ])->json('data.id');
        $this->actingAs($this->parent)->get("/api/v1/portal/invoices/{$otherInvoice}/pdf")->assertNotFound();
    }

    public function test_parent_requests_an_appointment_and_front_desk_is_told(): void
    {
        $this->actingAs($this->parent)->postJson("/api/v1/portal/children/{$this->ayan->id}/appointment-requests", [])->assertJsonValidationErrors('message');
        $this->actingAs($this->parent)->postJson("/api/v1/portal/children/{$this->ayan->id}/appointment-requests", [
            'service_id' => $this->speech->id, 'preferred_date' => today()->addDays(3)->toDateString(), 'message' => 'Thursday afternoon please',
        ])->assertCreated();

        $this->assertDatabaseHas('appointment_requests', ['patient_id' => $this->ayan->id, 'source' => 'portal', 'parent_name' => 'Nusrat Jahan', 'status' => 'new']);
        $this->assertSame(1, $this->reception->notifications()->count());
        $this->actingAs($this->parent)->getJson('/api/v1/portal/profile')->assertOk()->assertJsonCount(1, 'data.requests');
    }

    public function test_schedule_lists_upcoming_appointments(): void
    {
        Appointment::create([
            'appointment_code' => 'APT-UP-1', 'patient_id' => $this->ayan->id, 'enrollment_id' => $this->therapy->id, 'service_id' => $this->speech->id,
            'therapist_id' => $this->imran->id, 'branch_id' => $this->branch->id, 'date' => today()->addDays(2)->toDateString(),
            'start_time' => '16:00:00', 'end_time' => '16:45:00', 'type' => 'therapy', 'status' => 'confirmed',
        ]);
        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->ayan->id}/schedule")
            ->assertOk()->assertJsonCount(1, 'data.upcoming')->assertJsonPath('data.upcoming.0.start_time', '16:00')->assertJsonPath('data.class', null);
        $this->actingAs($this->parent)->getJson("/api/v1/portal/children/{$this->ayan->id}/home")
            ->assertOk()->assertJsonPath('data.next_appointment.start_time', '16:00');
    }
}
