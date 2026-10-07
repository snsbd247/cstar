<?php

namespace Tests\Feature\Therapy;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\IndividualPlan;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 22: the morning staff reminder tells a therapist which plans are due for review — once a week, not every day. */
class PlanReviewReminderTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_due_plans_remind_the_treating_therapist_weekly(): void
    {
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create();
        $user = $this->userWithRole(Role::Therapist, $branch);
        $therapist = $this->therapistFor($speech, $user, $branch);
        $child = Patient::factory()->create(['home_branch_id' => $branch->id, 'name' => 'Due Child']);
        $enrollment = $this->enrollTherapy($child, $speech, $therapist, $branch);
        $plan = IndividualPlan::create(['enrollment_id' => $enrollment->id, 'patient_id' => $child->id, 'title' => 'Speech plan', 'start_date' => today()->subMonths(3), 'review_date' => today()->addDays(3), 'status' => 'active']);
        IndividualPlan::create(['enrollment_id' => $enrollment->id, 'patient_id' => $child->id, 'title' => 'Later', 'start_date' => today(), 'review_date' => today()->addMonths(2), 'status' => 'active']);

        $this->artisan('cstar:reminders --type=staff')->expectsOutputToContain('Plan review reminders: 1')->assertSuccessful();
        $note = $user->notifications()->where('data->kind', 'plans.review_due')->sole();
        $this->assertStringContainsString('Due Child', $note->data['body']);

        $this->artisan('cstar:reminders --type=staff')->expectsOutputToContain('Plan review reminders: 0');
        $plan->update(['status' => 'closed']);
        $this->assertSame(1, $user->notifications()->where('data->kind', 'plans.review_due')->count());
    }
}
