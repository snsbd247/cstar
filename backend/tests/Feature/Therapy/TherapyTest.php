<?php

namespace Tests\Feature\Therapy;

use App\Enums\Role;
use App\Enums\ServiceCategory;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class TherapyTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private Service $speech;

    private User $imranUser;

    private Therapist $imran;

    private Patient $sara;

    private Enrollment $enrollment;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech', 'default_duration_min' => 45]);
        $this->imranUser = $this->userWithRole(Role::Therapist, $this->branch);
        $this->imran = $this->therapistFor($this->speech, $this->imranUser, $this->branch);
        foreach (range(0, 6) as $d) {
            $this->imran->schedules()->create(['branch_id' => $this->branch->id, 'weekday' => $d, 'start_time' => '09:00', 'end_time' => '18:00', 'slot_minutes' => 45]);
        }
        $this->sara = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->enrollment = $this->enrollTherapy($this->sara, $this->speech, $this->imran, $this->branch);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
    }

    private function book(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reception)->postJson('/api/v1/appointments', [
            'patient_id' => $this->sara->id, 'service_id' => $this->speech->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today()->addDays(2)->toDateString(), 'start_time' => '10:00', ...$overrides,
        ]);
    }

    /** Appointment on a past/present day, created directly (booking only allows future dates). */
    private function pastAppointment(string $date, string $status = 'confirmed', ?Therapist $therapist = null): Appointment
    {
        return Appointment::create([
            'appointment_code' => 'APT-T-'.uniqid(), 'patient_id' => $this->sara->id, 'enrollment_id' => $this->enrollment->id,
            'service_id' => $this->speech->id, 'therapist_id' => ($therapist ?? $this->imran)->id, 'branch_id' => $this->branch->id,
            'date' => $date, 'start_time' => '10:00:00', 'end_time' => '10:45:00', 'type' => 'therapy', 'status' => $status,
        ]);
    }

    public function test_reception_books_a_free_slot_linked_to_the_therapy_enrollment(): void
    {
        $this->book()->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.end_time', '10:45')
            ->assertJsonPath('data.enrollment_id', $this->enrollment->id);
    }

    public function test_double_booking_and_overlaps_are_rejected(): void
    {
        $this->book()->assertCreated();
        $this->book()->assertJsonValidationErrors('start_time');                       // same slot
        $this->book(['start_time' => '10:30'])->assertJsonValidationErrors('start_time'); // overlaps 10:00–10:45

        // The child cannot be in two therapy rooms at once, even with another therapist.
        $otherTherapist = $this->therapistFor($this->speech, null, $this->branch);
        $otherTherapist->schedules()->create(['branch_id' => $this->branch->id, 'weekday' => today()->addDays(2)->dayOfWeek, 'start_time' => '09:00', 'end_time' => '18:00', 'slot_minutes' => 45]);
        $this->book(['therapist_id' => $otherTherapist->id])->assertJsonValidationErrors('start_time');
    }

    public function test_database_itself_blocks_a_second_live_appointment_in_the_same_slot(): void
    {
        $this->pastAppointment(today()->addDay()->toDateString());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->pastAppointment(today()->addDay()->toDateString());
    }

    public function test_booking_rules_hours_leave_holiday_and_services(): void
    {
        $this->book(['start_time' => '20:00'])->assertJsonValidationErrors('start_time');

        $day = today()->addDays(3);
        $this->imran->leaves()->create(['start_date' => $day, 'end_date' => $day, 'reason' => 'Training']);
        $this->book(['date' => $day->toDateString()])->assertJsonValidationErrors('date');

        Holiday::create(['date' => today()->addDays(4), 'title' => 'Victory Day', 'type' => 'public']);
        $this->book(['date' => today()->addDays(4)->toDateString()])->assertJsonValidationErrors('date');

        $ot = Service::factory()->create();
        $this->book(['service_id' => $ot->id])->assertJsonValidationErrors('therapist_id');

        $training = Service::factory()->create(['category' => ServiceCategory::Training]);
        $this->book(['service_id' => $training->id])->assertJsonValidationErrors('service_id');

        $this->book([], $this->userWithRole(Role::Receptionist))->assertForbidden(); // other branch
    }

    public function test_availability_marks_booked_slots(): void
    {
        // Slots start every 45 minutes from 09:00: 09:00, 09:45, 10:30 …
        $this->book(['start_time' => '09:45'])->assertCreated();

        $slots = collect($this->actingAs($this->reception)->getJson('/api/v1/availability?'.http_build_query([
            'therapist_id' => $this->imran->id, 'branch_id' => $this->branch->id, 'date' => today()->addDays(2)->toDateString(), 'service_id' => $this->speech->id,
        ]))->assertOk()->json('data.slots'))->keyBy('start');

        $this->assertTrue($slots['09:00']['available']);
        $this->assertFalse($slots['09:45']['available']);
        $this->assertTrue($slots['10:30']['available']);
    }

    public function test_cancel_needs_a_reason_flags_late_cancellations_and_frees_the_slot(): void
    {
        $id = $this->book(['date' => today()->addDay()->toDateString()])->json('data.id');

        $this->actingAs($this->reception)->postJson("/api/v1/appointments/{$id}/cancel")->assertJsonValidationErrors('reason');
        $this->actingAs($this->reception)->postJson("/api/v1/appointments/{$id}/cancel", ['reason' => 'Child unwell'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.is_late_cancellation', today()->addDay()->setTime(10, 0)->diffInHours(now(), true) < 24);

        $this->book(['date' => today()->addDay()->toDateString()])->assertCreated(); // slot is free again
    }

    public function test_reschedule_keeps_the_link_to_the_old_appointment(): void
    {
        $id = $this->book()->json('data.id');

        $newId = $this->actingAs($this->reception)->postJson("/api/v1/appointments/{$id}/reschedule", [
            'date' => today()->addDays(2)->toDateString(), 'start_time' => '12:00',
        ])->assertCreated()->assertJsonPath('data.start_time', '12:00')->json('data.id');

        $this->assertSame('rescheduled', Appointment::find($id)->status->value);
        $this->assertSame($id, Appointment::find($newId)->rescheduled_from_id);
    }

    public function test_only_the_treating_therapist_writes_the_session_note(): void
    {
        $appointment = $this->pastAppointment(today()->toDateString());
        $note = ['observation' => 'Good session', 'parent_summary' => 'Sara did well'];

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $this->branch))->postJson("/api/v1/appointments/{$appointment->id}/session", $note)->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Trainer, $this->branch))->postJson("/api/v1/appointments/{$appointment->id}/session", $note)->assertForbidden();
        $otherUser = $this->userWithRole(Role::Therapist, $this->branch);
        $this->therapistFor($this->speech, $otherUser, $this->branch);
        $this->actingAs($otherUser)->postJson("/api/v1/appointments/{$appointment->id}/session", $note)->assertForbidden();

        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$appointment->id}/session", $note)
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertSame('checked_in', $appointment->fresh()->status->value);
    }

    public function test_finalizing_needs_parent_summary_completes_and_locks(): void
    {
        $appointment = $this->pastAppointment(today()->subDay()->toDateString());
        $url = "/api/v1/appointments/{$appointment->id}/session";

        $this->actingAs($this->imranUser)->postJson($url, ['observation' => 'x', 'finalize' => true])->assertJsonValidationErrors('parent_summary');
        $this->actingAs($this->imranUser)->postJson($url, ['observation' => 'x', 'parent_summary' => 'Well done', 'finalize' => true])
            ->assertCreated()->assertJsonPath('data.status', 'final');

        $this->assertSame('completed', $appointment->fresh()->status->value);
        $this->actingAs($this->imranUser)->postJson($url, ['observation' => 'edit'])->assertJsonValidationErrors('session');
        $this->assertDatabaseHas('timeline_events', ['patient_id' => $this->sara->id, 'event_type' => 'therapy.session', 'visibility' => 'parent']);
    }

    public function test_no_note_for_future_or_cancelled_appointments(): void
    {
        $future = $this->pastAppointment(today()->addDays(5)->toDateString());
        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$future->id}/session", ['observation' => 'x'])->assertJsonValidationErrors('appointment');

        $cancelled = $this->pastAppointment(today()->subDays(2)->toDateString(), 'cancelled');
        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$cancelled->id}/session", ['observation' => 'x'])->assertJsonValidationErrors('appointment');
    }

    public function test_therapy_plan_goal_scores_in_session(): void
    {
        $plan = $this->actingAs($this->imranUser)->postJson("/api/v1/enrollments/{$this->enrollment->id}/plans", [
            'title' => 'Speech plan', 'start_date' => today()->toDateString(), 'goals' => [['title' => 'Two-word phrases']],
        ])->assertCreated()->json('data');

        $appointment = $this->pastAppointment(today()->toDateString());
        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$appointment->id}/session", [
            'goal_scores' => [['goal_id' => $plan['goals'][0]['id'], 'score' => 3]],
        ])->assertCreated()->assertJsonPath('data.goal_scores.0.score', 3);
    }

    public function test_check_in_only_on_the_day_and_therapist_can_mark_no_show(): void
    {
        $id = $this->book()->json('data.id');
        $this->actingAs($this->reception)->postJson("/api/v1/appointments/{$id}/check-in")->assertJsonValidationErrors('status');

        $today = $this->pastAppointment(today()->toDateString());
        $this->actingAs($this->imranUser)->postJson("/api/v1/appointments/{$today->id}/no-show")->assertOk()->assertJsonPath('data.status', 'no_show');
    }

    public function test_weekly_slots_generate_upcoming_appointments_and_skip_holidays(): void
    {
        $this->actingAs($this->reception)->putJson("/api/v1/enrollments/{$this->enrollment->id}/slots", [
            'slots' => [['weekday' => today()->addDays(2)->dayOfWeek, 'start_time' => '15:00']],
        ])->assertOk();
        Holiday::create(['date' => today()->addDays(9), 'title' => 'Holiday', 'type' => 'center']);

        $result = $this->actingAs($this->reception)->postJson("/api/v1/enrollments/{$this->enrollment->id}/generate-appointments", ['weeks' => 2])
            ->assertOk()->json('data');

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['skipped']);

        // Running it again creates nothing new.
        $this->actingAs($this->reception)->postJson("/api/v1/enrollments/{$this->enrollment->id}/generate-appointments", ['weeks' => 2])
            ->assertJsonPath('data.created', 0);
    }

    public function test_therapist_sees_new_patient_through_an_assessment_appointment(): void
    {
        $newChild = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->actingAs($this->imranUser)->getJson("/api/v1/patients/{$newChild->id}")->assertForbidden();

        $this->actingAs($this->reception)->postJson('/api/v1/appointments', [
            'patient_id' => $newChild->id, 'service_id' => $this->speech->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today()->addDay()->toDateString(), 'start_time' => '11:00',
        ])->assertCreated()->assertJsonPath('data.type', 'assessment');

        $this->actingAs($this->imranUser->fresh())->getJson("/api/v1/patients/{$newChild->id}")->assertOk();
    }

    public function test_therapist_today_and_trainer_cannot_read_therapy_notes(): void
    {
        $this->pastAppointment(today()->toDateString());

        $this->actingAs($this->imranUser)->getJson('/api/v1/therapist/today')->assertOk()->assertJsonPath('data.totals.appointments', 1);
        $this->actingAs($this->userWithRole(Role::Trainer, $this->branch))->getJson('/api/v1/therapy-sessions')->assertForbidden();
    }
}
