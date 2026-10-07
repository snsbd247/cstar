<?php

namespace Tests\Feature\Therapy;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 22 (Plan #৬): a substitute therapist takes an absent colleague's bookings; a substitute trainer covers a class. */
class SubstituteTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private function booking(Patient $child, Service $service, Therapist $therapist, Branch $branch, string $date, string $start): Appointment
    {
        return Appointment::create([
            'appointment_code' => 'APT-SUB-'.uniqid(), 'patient_id' => $child->id, 'service_id' => $service->id, 'therapist_id' => $therapist->id,
            'branch_id' => $branch->id, 'date' => $date, 'start_time' => $start, 'end_time' => date('H:i:s', strtotime($start) + 2700),
            'type' => 'therapy', 'status' => 'confirmed',
        ]);
    }

    public function test_appointments_move_to_a_free_colleague_who_gives_the_service(): void
    {
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create(['name' => 'Speech Therapy']);
        $ot = Service::factory()->create(['name' => 'Occupational Therapy']);
        $away = $this->therapistFor($speech, branch: $branch);
        $away->services()->attach($ot);
        $subUser = $this->userWithRole(Role::Therapist, $branch);
        $sub = $this->therapistFor($speech, $subUser, $branch);
        $child = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $day = today()->addDays(2)->toDateString();

        $free = $this->booking($child, $speech, $away, $branch, $day, '09:00:00');
        $clash = $this->booking($child, $speech, $away, $branch, $day, '11:00:00');
        $this->booking(Patient::factory()->create(), $speech, $sub, $branch, $day, '11:00:00');   // the substitute is busy at 11
        $otOnly = $this->booking($child, $ot, $away, $branch, $day, '14:00:00');                  // the substitute does not give OT
        $this->actingAs($this->userWithRole(Role::Receptionist, $branch));

        $options = $this->getJson("/api/v1/therapists/{$away->id}/substitute?from={$day}&to={$day}")->assertOk();
        $this->assertCount(3, $options->json('data.appointments'));
        $this->assertSame(1, $options->json('data.candidates.0.can_take'));

        $preview = $this->postJson("/api/v1/therapists/{$away->id}/substitute", ['from' => $day, 'to' => $day, 'substitute_id' => $sub->id, 'dry_run' => true])->assertOk();
        $this->assertCount(1, $preview->json('data.moved'));
        $this->assertSame($away->id, $free->fresh()->therapist_id);                              // a preview changes nothing

        $done = $this->postJson("/api/v1/therapists/{$away->id}/substitute", ['from' => $day, 'to' => $day, 'substitute_id' => $sub->id])->assertOk();
        $this->assertSame([$free->id], array_column($done->json('data.moved'), 'id'));
        $this->assertEqualsCanonicalizing([$clash->id, $otOnly->id], array_column($done->json('data.skipped'), 'id'));
        $free->refresh();
        $this->assertSame($sub->id, $free->therapist_id);
        $this->assertSame($away->id, $free->substitute_for_id);
        $this->assertSame(1, $subUser->notifications()->where('data->kind', 'appointment.substitute')->count());

        // The substitute can now open the child's session note; a substitute on leave is refused.
        $this->actingAs($subUser)->getJson("/api/v1/appointments/{$free->id}/session")->assertOk();
        TherapistLeave::create(['therapist_id' => $sub->id, 'start_date' => $day, 'end_date' => $day]);
        $this->actingAs($this->userWithRole(Role::Receptionist, $branch))
            ->postJson("/api/v1/therapists/{$away->id}/substitute", ['from' => $day, 'to' => $day, 'substitute_id' => $sub->id, 'dry_run' => true])
            ->assertJsonPath('data.skipped.0.reason', "{$sub->name} is on leave that day.");
    }

    public function test_a_substitute_trainer_reaches_the_class_only_while_covering(): void
    {
        $branch = Branch::factory()->create();
        $class = $this->classFor($branch);
        $this->enrollTraining(Patient::factory()->create(['home_branch_id' => $branch->id]), $class);
        $coverUser = $this->userWithRole(Role::Trainer, $branch);
        $cover = $this->trainerFor($branch, $coverUser);

        $this->actingAs($coverUser)->getJson("/api/v1/classes/{$class->id}/attendance?date=".today()->toDateString())->assertForbidden();
        $this->assertCount(0, $this->getJson('/api/v1/trainer/today')->json('data.classes') ?? []);

        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))->postJson("/api/v1/classes/{$class->id}/substitutes", [
            'trainer_id' => $class->lead_trainer_id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(),
        ])->assertJsonValidationErrors('trainer_id');
        $this->postJson("/api/v1/classes/{$class->id}/substitutes", [
            'trainer_id' => $cover->id, 'date_from' => today()->toDateString(), 'date_to' => today()->addDay()->toDateString(), 'reason' => 'Lead on leave',
        ])->assertCreated();

        $this->actingAs($coverUser->fresh())->getJson("/api/v1/classes/{$class->id}/attendance?date=".today()->toDateString())->assertOk();
        $this->assertSame(1, $this->getJson('/api/v1/patients')->json('meta.total'));
        $this->assertSame(1, $coverUser->notifications()->where('data->kind', 'class.substitute')->count());

        // Ended cover: access is gone again.
        $class->substitutes()->update(['date_from' => today()->subDays(5), 'date_to' => today()->subDays(3)]);
        $this->getJson("/api/v1/classes/{$class->id}/attendance?date=".today()->toDateString())->assertForbidden();
    }
}
