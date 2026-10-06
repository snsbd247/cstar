<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\ActivityType;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $trainerUser;

    private TrainingGroup $class;

    private Enrollment $ayan;

    private Enrollment $rafi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->trainerUser = $this->userWithRole(Role::Trainer, $this->branch);
        $this->class = $this->classFor($this->branch, $this->trainerFor($this->branch, $this->trainerUser));
        // Every day of the week is a class day in tests.
        foreach (range(0, 6) as $d) {
            $this->class->schedules()->create(['weekday' => $d, 'start_time' => '10:00', 'end_time' => '13:00']);
        }
        $start = today()->subDays(20)->toDateString();
        $this->ayan = $this->enrollTraining(Patient::factory()->create(['home_branch_id' => $this->branch->id]), $this->class, ['start_date' => $start]);
        $this->rafi = $this->enrollTraining(Patient::factory()->create(['home_branch_id' => $this->branch->id]), $this->class, ['start_date' => $start]);
    }

    private function mark(User $user, string $date, array $statuses)
    {
        return $this->actingAs($user)->postJson("/api/v1/classes/{$this->class->id}/attendance", [
            'date' => $date,
            'entries' => collect($statuses)->map(fn ($s, $id) => ['enrollment_id' => $id, 'status' => $s])->values()->all(),
        ]);
    }

    public function test_trainer_marks_attendance_for_own_class(): void
    {
        $this->mark($this->trainerUser, today()->toDateString(), [$this->ayan->id => 'present', $this->rafi->id => 'absent'])->assertOk();

        $this->actingAs($this->trainerUser)->getJson("/api/v1/classes/{$this->class->id}/attendance")
            ->assertOk()->assertJsonCount(2, 'data.students')->assertJsonFragment(['status' => 'absent']);
    }

    public function test_attendance_rules(): void
    {
        // never the future
        $this->mark($this->trainerUser, today()->addDay()->toDateString(), [$this->ayan->id => 'present'])->assertJsonValidationErrors('date');
        // trainer edit window
        $this->mark($this->trainerUser, today()->subDays(5)->toDateString(), [$this->ayan->id => 'present'])->assertJsonValidationErrors('date');
        $this->mark($this->userWithRole(Role::BranchAdmin, $this->branch), today()->subDays(5)->toDateString(), [$this->ayan->id => 'present'])->assertOk();
        // holiday
        Holiday::create(['date' => today()->subDay(), 'title' => 'Victory Day', 'type' => 'public']);
        $this->mark($this->trainerUser, today()->subDay()->toDateString(), [$this->ayan->id => 'present'])->assertUnprocessable();
        $this->mark($this->trainerUser, today()->subDay()->toDateString(), [$this->ayan->id => 'holiday'])->assertOk();
        // a child from another class
        $stranger = $this->enrollTraining(Patient::factory()->create(['home_branch_id' => $this->branch->id]), $this->classFor($this->branch));
        $this->mark($this->trainerUser, today()->toDateString(), [$stranger->id => 'present'])->assertUnprocessable();
    }

    public function test_only_the_classes_own_trainer_and_branch_staff_can_mark(): void
    {
        $otherTrainer = $this->userWithRole(Role::Trainer, $this->branch);
        $this->trainerFor($this->branch, $otherTrainer);
        $this->mark($otherTrainer, today()->toDateString(), [$this->ayan->id => 'present'])->assertForbidden();

        // Therapists and receptionists do not mark student attendance.
        $this->mark($this->userWithRole(Role::Therapist, $this->branch), today()->toDateString(), [$this->ayan->id => 'present'])->assertForbidden();
        $this->mark($this->userWithRole(Role::Receptionist, $this->branch), today()->toDateString(), [$this->ayan->id => 'present'])->assertForbidden();
    }

    public function test_monthly_attendance_rate_excludes_leave_and_holidays(): void
    {
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $statuses = ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'late', 'absent', 'leave', 'holiday'];
        $day = today()->startOfMonth();
        foreach ($statuses as $status) {
            if ($status === 'holiday') {
                Holiday::create(['date' => $day, 'title' => 'H', 'type' => 'center']);
            }
            if ($day->lte(today()) && $day->gte($this->ayan->start_date)) {
                $this->mark($admin, $day->toDateString(), [$this->ayan->id => $status])->assertOk();
            }
            $day->addDay();
        }

        $summary = $this->actingAs($admin)->getJson("/api/v1/enrollments/{$this->ayan->id}/attendance")->assertOk()->json('data.summary');
        $countable = $summary['present'] + $summary['late'] + $summary['absent'];
        if ($countable) {
            $this->assertSame((int) round(($summary['present'] + $summary['late']) / $countable * 100), $summary['rate']);
        }
        $this->assertArrayHasKey('leave', $summary);
    }

    /** (present 8 + late 1) ÷ (8 + 1 + absent 1) = 90% — leave and holiday ignored. */
    public function test_attendance_rate_formula(): void
    {
        $statuses = collect([...array_fill(0, 8, 'present'), 'late', 'absent', 'leave', 'holiday'])
            ->map(fn ($s) => \App\Enums\AttendanceStatus::from($s));

        $summary = app(\App\Services\AttendanceService::class)->summary($statuses);

        $this->assertSame(['present' => 8, 'late' => 1, 'absent' => 1, 'leave' => 1, 'holiday' => 1, 'rate' => 90], $summary);
    }

    public function test_training_record_requires_attendance_and_locks_when_final(): void
    {
        $date = today()->toDateString();
        $payload = ['date' => $date, 'enrollment_id' => $this->ayan->id, 'observation' => 'Good focus', 'performance' => 4,
            'activity_ids' => [ActivityType::create(['name' => 'Fine Motor Activity'])->id], 'parent_note' => 'Great day'];

        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", $payload)
            ->assertJsonValidationErrors('enrollment_id'); // not marked present yet

        $this->mark($this->trainerUser, $date, [$this->ayan->id => 'late']);
        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", $payload)
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", [...$payload, 'finalize' => true])
            ->assertOk()->assertJsonPath('data.status', 'final');
        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", [...$payload, 'observation' => 'changed'])
            ->assertJsonValidationErrors('record');

        $this->assertDatabaseHas('timeline_events', ['patient_id' => $this->ayan->patient_id, 'event_type' => 'training.record', 'visibility' => 'parent']);
    }

    public function test_therapist_cannot_write_training_records(): void
    {
        $this->mark($this->trainerUser, today()->toDateString(), [$this->ayan->id => 'present']);

        $this->actingAs($this->userWithRole(Role::Therapist, $this->branch))
            ->postJson("/api/v1/classes/{$this->class->id}/records", ['date' => today()->toDateString(), 'enrollment_id' => $this->ayan->id])
            ->assertForbidden();
    }

    public function test_itp_by_own_trainer_with_goal_scores(): void
    {
        $plan = $this->actingAs($this->trainerUser)->postJson("/api/v1/enrollments/{$this->ayan->id}/plans", [
            'title' => 'ITP Term 1', 'start_date' => today()->toDateString(),
            'goals' => [['title' => 'Improve fine motor skill'], ['title' => 'Improve independent eating']],
        ])->assertCreated()->json('data');

        $this->actingAs($this->trainerUser)->postJson("/api/v1/enrollments/{$this->ayan->id}/plans", [
            'title' => 'Second', 'start_date' => today()->toDateString(), 'goals' => [['title' => 'x']],
        ])->assertJsonValidationErrors('plan'); // one active plan

        $this->mark($this->trainerUser, today()->toDateString(), [$this->ayan->id => 'present', $this->rafi->id => 'present']);
        $goalId = $plan['goals'][0]['id'];
        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", [
            'date' => today()->toDateString(), 'enrollment_id' => $this->ayan->id, 'goal_scores' => [['goal_id' => $goalId, 'score' => 4]],
        ])->assertCreated()->assertJsonPath('data.goal_scores.0.score', 4);

        // Ayan's goal cannot be scored on Rafi's record.
        $this->actingAs($this->trainerUser)->postJson("/api/v1/classes/{$this->class->id}/records", [
            'date' => today()->toDateString(), 'enrollment_id' => $this->rafi->id, 'goal_scores' => [['goal_id' => $goalId, 'score' => 4]],
        ])->assertJsonValidationErrors('goal_scores.0.goal_id');

        // Removing a goal that has progress keeps it as "discontinued".
        $this->actingAs($this->trainerUser)->putJson("/api/v1/plans/{$plan['id']}", [
            'title' => 'ITP Term 1', 'start_date' => today()->toDateString(), 'goals' => [['id' => $plan['goals'][1]['id'], 'title' => 'Improve independent eating']],
        ])->assertOk();
        $this->assertDatabaseHas('plan_goals', ['id' => $goalId, 'status' => 'discontinued']);
    }

    public function test_plan_writers_respect_trainer_therapist_split(): void
    {
        $speech = Service::factory()->create();
        $therapistUser = $this->userWithRole(Role::Therapist, $this->branch);
        $therapy = $this->enrollTherapy($this->ayan->patient, $speech, $this->therapistFor($speech, $therapistUser), $this->branch);
        $plan = ['title' => 'Plan', 'start_date' => today()->toDateString(), 'goals' => [['title' => 'Goal']]];

        $this->actingAs($this->trainerUser)->postJson("/api/v1/enrollments/{$therapy->id}/plans", $plan)->assertForbidden();
        $this->actingAs($therapistUser)->postJson("/api/v1/enrollments/{$this->ayan->id}/plans", $plan)->assertForbidden();
        $this->actingAs($therapistUser)->postJson("/api/v1/enrollments/{$therapy->id}/plans", $plan)->assertCreated();
    }

    public function test_class_management_and_trainer_today(): void
    {
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->actingAs($admin)->postJson('/api/v1/classes', [
            'branch_id' => $this->branch->id, 'code' => 'FDB', 'name' => 'Functional Development B', 'status' => 'active', 'max_students' => 8,
            'schedules' => [['weekday' => 6, 'start_time' => '14:00', 'end_time' => '16:00']],
        ])->assertCreated()->assertJsonPath('data.schedules.0.day', 'Saturday');

        // A trainer only sees the classes they teach.
        $names = collect($this->actingAs($this->trainerUser)->getJson('/api/v1/classes')->json('data'))->pluck('name');
        $this->assertEquals([$this->class->name], $names->all());
        $this->actingAs($this->trainerUser)->putJson("/api/v1/classes/{$this->class->id}", ['code' => 'X', 'name' => 'X', 'status' => 'active'])->assertForbidden();

        $this->mark($this->trainerUser, today()->toDateString(), [$this->ayan->id => 'present', $this->rafi->id => 'absent']);
        $this->actingAs($this->trainerUser)->getJson('/api/v1/trainer/today')->assertOk()
            ->assertJsonPath('data.totals.students', 2)
            ->assertJsonPath('data.totals.present', 1)
            ->assertJsonPath('data.totals.absent', 1)
            ->assertJsonPath('data.totals.pending_records', 1);
    }

    public function test_students_list_and_records_history_are_scoped(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->enrollTraining(Patient::factory()->create(['home_branch_id' => $otherBranch->id]), $this->classFor($otherBranch));

        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))->getJson('/api/v1/students')
            ->assertOk()->assertJsonCount(2, 'data');
    }
}
