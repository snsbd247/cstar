<?php

namespace Tests\Feature\Therapy;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 22 (Plan #২০): a clinical supervisor reviews colleagues' finalized notes; "needs changes" goes to the author. */
class ClinicalReviewTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_supervisor_reviews_a_colleagues_final_note(): void
    {
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create();
        $authorUser = $this->userWithRole(Role::Therapist, $branch);
        $author = $this->therapistFor($speech, $authorUser, $branch);
        $supUser = $this->userWithRole(Role::Therapist, $branch);
        $sup = $this->therapistFor($speech, $supUser, $branch);
        $child = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $enrollment = $this->enrollTherapy($child, $speech, $author, $branch);
        $appointment = Appointment::create([
            'appointment_code' => 'APT-REV-1', 'patient_id' => $child->id, 'enrollment_id' => $enrollment->id, 'service_id' => $speech->id,
            'therapist_id' => $author->id, 'branch_id' => $branch->id, 'date' => today()->subDay(), 'start_time' => '09:00', 'end_time' => '09:45',
            'type' => 'therapy', 'status' => 'completed',
        ]);
        $session = TherapySession::create([
            'appointment_id' => $appointment->id, 'patient_id' => $child->id, 'therapist_id' => $author->id, 'service_id' => $speech->id,
            'branch_id' => $branch->id, 'date' => $appointment->date, 'status' => 'final', 'parent_summary' => 'Good session.',
        ]);

        // Not yet a supervisor: no access to the review list.
        $this->actingAs($supUser)->getJson('/api/v1/clinical-reviews')->assertForbidden();
        $sup->update(['is_supervisor' => true]);
        $supUser = $supUser->fresh();
        $this->actingAs($supUser)->getJson('/api/v1/auth/me')->assertJsonPath('data.is_clinical_supervisor', true);

        $this->getJson('/api/v1/clinical-reviews')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $session->id);
        $this->postJson('/api/v1/clinical-reviews', ['type' => 'session', 'id' => $session->id, 'outcome' => 'needs_changes'])->assertJsonValidationErrors('comment');
        $this->postJson('/api/v1/clinical-reviews', ['type' => 'session', 'id' => $session->id, 'outcome' => 'needs_changes', 'comment' => 'Add the activities used.'])->assertCreated();

        $this->assertSame(1, $authorUser->notifications()->where('data->kind', 'clinical.review')->count());
        $this->getJson('/api/v1/clinical-reviews')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/clinical-reviews?status=reviewed')->assertJsonPath('data.0.review.outcome', 'needs_changes');
        // The author sees the review under their note; a supervisor cannot review their own notes.
        $this->actingAs($authorUser)->getJson("/api/v1/clinical-reviews/session/{$session->id}")->assertOk()->assertJsonPath('data.0.comment', 'Add the activities used.');
        $author->update(['is_supervisor' => true]);
        $this->actingAs($authorUser->fresh())->postJson('/api/v1/clinical-reviews', ['type' => 'session', 'id' => $session->id, 'outcome' => 'ok'])->assertStatus(422);
    }
}
