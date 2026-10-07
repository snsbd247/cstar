<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TherapistSchedule;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 20: parents book a free slot with their child's therapist and cancel upcoming appointments. */
class PortalBookingTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Patient $child;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create(['name' => 'Speech Therapy', 'default_duration_min' => 45]);
        $therapist = $this->therapistFor($speech, branch: $branch);
        foreach (range(0, 6) as $day) {
            TherapistSchedule::create(['therapist_id' => $therapist->id, 'branch_id' => $branch->id, 'weekday' => $day, 'start_time' => '09:00', 'end_time' => '17:00', 'slot_minutes' => 60]);
        }
        $this->child = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $this->enrollTherapy($this->child, $speech, $therapist, $branch);
        $this->parent = $this->userWithRole(Role::Parent, $branch);
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $this->child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);
        $this->parent = $this->parent->fresh();
    }

    public function test_parent_books_a_free_slot_that_waits_for_confirmation(): void
    {
        $this->actingAs($this->parent);
        $options = $this->getJson("/api/v1/portal/children/{$this->child->id}/booking")->assertOk();
        $enrollmentId = $options->json('data.programmes.0.enrollment_id');
        $day = today()->addDays(3)->toDateString();

        $slots = $this->getJson("/api/v1/portal/children/{$this->child->id}/booking/slots?enrollment_id={$enrollmentId}&date={$day}")->assertOk()->json('data.slots');
        $this->assertSame('09:00', $slots[0]['start']);

        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => $day, 'start_time' => '09:00'])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $appointment = Appointment::sole();
        $this->assertSame('portal', $appointment->source);

        // The taken slot disappears; beyond the window or too many unconfirmed bookings is refused.
        $this->assertNotContains('09:00', array_column($this->getJson("/api/v1/portal/children/{$this->child->id}/booking/slots?enrollment_id={$enrollmentId}&date={$day}")->json('data.slots'), 'start'));
        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => today()->addDays(40)->toDateString(), 'start_time' => '10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => $day, 'start_time' => '10:00'])->assertCreated();
        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => $day, 'start_time' => '11:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        // Someone else's child is invisible.
        $this->getJson('/api/v1/portal/children/'.Patient::factory()->create()->id.'/booking')->assertNotFound();
    }

    public function test_parent_cancels_and_late_cancellation_is_marked(): void
    {
        app(SystemSettings::class)->update('appointment', ['late_cancel_hours' => 48]);
        $this->actingAs($this->parent);
        $enrollmentId = $this->getJson("/api/v1/portal/children/{$this->child->id}/booking")->json('data.programmes.0.enrollment_id');
        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => today()->addDay()->toDateString(), 'start_time' => '16:00'])->assertCreated();
        $appointment = Appointment::sole();

        $this->postJson("/api/v1/portal/children/{$this->child->id}/appointments/{$appointment->id}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.late', true);
        $this->postJson("/api/v1/portal/children/{$this->child->id}/appointments/{$appointment->id}/cancel")->assertUnprocessable();

        app(SystemSettings::class)->update('appointment', ['portal_booking_enabled' => '0']);
        $this->postJson("/api/v1/portal/children/{$this->child->id}/booking", ['enrollment_id' => $enrollmentId, 'date' => today()->addDays(2)->toDateString(), 'start_time' => '10:00'])->assertForbidden();
    }
}
