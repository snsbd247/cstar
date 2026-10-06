<?php

namespace Tests\Feature\Therapy;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use Database\Seeders\AssessmentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private Service $speech;

    private Service $ot;

    private User $imranUser;

    private Therapist $imran;

    private Patient $sara;

    private User $reception;

    private AssessmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssessmentTypeSeeder::class);
        $this->type = AssessmentType::where('name', 'Speech & Language Assessment')->firstOrFail();
        $this->branch = Branch::factory()->create();
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech']);
        $this->ot = Service::factory()->create(['name' => 'Occupational Therapy', 'slug' => 'ot']);
        $this->imranUser = $this->userWithRole(Role::Therapist, $this->branch);
        $this->imran = $this->therapistFor($this->speech, $this->imranUser, $this->branch);
        $this->sara = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->enrollTherapy($this->sara, $this->speech, $this->imran, $this->branch);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'assessment_type_id' => $this->type->id,
            'chief_complaint' => 'Limited speech',
            'section_findings' => ['expressive_language' => 'Single words only', 'not_a_section' => 'dropped'],
            'summary' => 'Expressive delay.',
            'parent_summary' => 'Short note for parents.',
            'recommendation_items' => [
                ['enrollment_type' => 'therapy', 'service_id' => $this->ot->id, 'frequency' => '1 / week', 'priority' => 'normal'],
            ],
            ...$overrides,
        ];
    }

    private function createAssessment(array $overrides = []): Assessment
    {
        $id = $this->actingAs($this->imranUser)->postJson("/api/v1/patients/{$this->sara->id}/assessments", $this->payload($overrides))
            ->assertCreated()->json('data.id');

        return Assessment::findOrFail($id);
    }

    public function test_therapist_writes_a_draft_then_finalizes_and_it_locks(): void
    {
        $a = $this->createAssessment();
        $this->assertSame('draft', $a->status);
        $this->assertSame(['expressive_language' => 'Single words only'], $a->section_findings);
        $this->assertMatchesRegularExpression('/^ASM-\d{4}-\d{5}$/', $a->assessment_code);

        $this->actingAs($this->imranUser)->putJson("/api/v1/assessments/{$a->id}", ['finalize' => true])
            ->assertOk()->assertJsonPath('data.status', 'final')->assertJsonPath('data.can_edit', false);
        $this->assertDatabaseHas('timeline_events', ['patient_id' => $this->sara->id, 'event_type' => 'assessment.finalized', 'visibility' => 'internal']);

        $this->actingAs($this->imranUser)->putJson("/api/v1/assessments/{$a->id}", ['summary' => 'changed'])
            ->assertJsonValidationErrors('assessment');
    }

    public function test_finalizing_needs_a_summary(): void
    {
        $this->actingAs($this->imranUser)->postJson("/api/v1/patients/{$this->sara->id}/assessments", $this->payload(['summary' => null, 'finalize' => true]))
            ->assertJsonValidationErrors('summary');
    }

    public function test_only_the_assessing_therapist_edits_and_reception_cannot_read_findings(): void
    {
        $a = $this->createAssessment();
        $other = $this->userWithRole(Role::Therapist, $this->branch);
        $this->therapistFor($this->speech, $other, $this->branch);

        $this->actingAs($other)->putJson("/api/v1/assessments/{$a->id}", ['summary' => 'x'])->assertForbidden();
        $this->actingAs($this->reception)->getJson("/api/v1/assessments/{$a->id}")->assertForbidden();
        $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->sara->id}/assessments", $this->payload())->assertForbidden();
    }

    public function test_reception_enrolls_from_a_recommendation_and_it_is_linked(): void
    {
        $a = $this->createAssessment(['finalize' => true]);
        $rec = $a->recommendationItems()->firstOrFail();
        $otTherapist = $this->therapistFor($this->ot, null, $this->branch);

        $this->actingAs($this->reception)->getJson("/api/v1/patients/{$this->sara->id}/recommendations")
            ->assertOk()
            ->assertJsonPath('data.0.service.name', 'Occupational Therapy')
            ->assertJsonPath('data.0.enrollment', null)
            ->assertJsonMissingPath('data.0.assessment.summary');

        $enrollmentId = $this->actingAs($this->reception)->postJson('/api/v1/enrollments', [
            'patient_id' => $this->sara->id, 'branch_id' => $this->branch->id, 'type' => 'therapy',
            'service_id' => $this->ot->id, 'therapist_id' => $otTherapist->id, 'start_date' => today()->toDateString(),
            'source_assessment_id' => $a->id, 'recommendation_id' => $rec->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($a->id, Enrollment::find($enrollmentId)->source_assessment_id);
        $this->assertSame($enrollmentId, $rec->fresh()->enrollment_id);
    }

    public function test_draft_recommendations_are_not_shown_to_reception(): void
    {
        $this->createAssessment();
        $this->actingAs($this->reception)->getJson("/api/v1/patients/{$this->sara->id}/recommendations")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sharing_puts_a_parent_visible_event_on_the_timeline(): void
    {
        $draft = $this->createAssessment();
        $this->actingAs($this->imranUser)->postJson("/api/v1/assessments/{$draft->id}/share", ['shared' => true])->assertJsonValidationErrors('assessment');

        $this->actingAs($this->imranUser)->putJson("/api/v1/assessments/{$draft->id}", ['finalize' => true])->assertOk();
        $this->actingAs($this->imranUser)->postJson("/api/v1/assessments/{$draft->id}/share", ['shared' => true])
            ->assertOk()->assertJsonPath('data.shared_with_parent', true);
        $this->assertDatabaseHas('timeline_events', ['patient_id' => $this->sara->id, 'event_type' => 'assessment.shared', 'visibility' => 'parent']);
    }

    public function test_assessment_and_progress_report_pdfs_render(): void
    {
        $a = $this->createAssessment(['finalize' => true, 'parent_summary' => 'সারার কথা বলায় উন্নতি হচ্ছে।']);

        $pdf = $this->actingAs($this->imranUser)->get("/api/v1/assessments/{$a->id}/pdf");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $report = $this->actingAs($this->imranUser)->get("/api/v1/patients/{$this->sara->id}/progress-report");
        $report->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $report->getContent());

        $this->actingAs($this->reception)->get("/api/v1/assessments/{$a->id}/pdf")->assertForbidden();
    }
}
