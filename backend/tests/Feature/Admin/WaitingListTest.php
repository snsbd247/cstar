<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\WaitingListEntry;
use App\Services\WaitingListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 20: the waiting list orders families first come first served (priority first) and closes on enrollment. */
class WaitingListTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_queue_positions_actions_and_closing_on_enrollment(): void
    {
        $branch = Branch::factory()->create();
        $speech = Service::factory()->create(['name' => 'Speech Therapy']);
        $therapist = $this->therapistFor($speech, branch: $branch);
        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch));
        [$a, $b, $c] = Patient::factory()->count(3)->create(['home_branch_id' => $branch->id]);
        $add = fn (Patient $p, string $priority = 'normal') => $this->postJson('/api/v1/waiting-list', [
            'patient_id' => $p->id, 'branch_id' => $branch->id, 'type' => 'therapy', 'service_id' => $speech->id, 'preferred_time' => 'any', 'priority' => $priority,
        ]);

        $add($a)->assertCreated();
        $this->travel(1)->minutes();
        $add($b)->assertCreated();
        $this->travel(1)->minutes();
        $add($c, 'high')->assertCreated();
        $add($a)->assertUnprocessable()->assertJsonValidationErrors('patient_id');
        $this->postJson('/api/v1/waiting-list', ['patient_id' => $a->id, 'branch_id' => $branch->id, 'type' => 'therapy', 'preferred_time' => 'any', 'priority' => 'normal'])
            ->assertJsonValidationErrors('service_id');

        $list = $this->getJson('/api/v1/waiting-list')->assertOk()->json('data');
        $this->assertSame([$c->id, $a->id, $b->id], array_column(array_column($list, 'patient'), 'id'));
        $this->assertSame([1, 2, 3], array_column($list, 'position'));

        $entryB = WaitingListEntry::where('patient_id', $b->id)->sole();
        $this->putJson("/api/v1/waiting-list/{$entryB->id}", ['action' => 'offer'])->assertOk()->assertJsonPath('data.status', 'offered');
        $this->putJson("/api/v1/waiting-list/{$entryB->id}", ['action' => 'remove', 'reason' => 'Moved away'])->assertJsonPath('data.status', 'removed');
        $this->assertSame('Moved away', $entryB->fresh()->resolution);
        $this->putJson("/api/v1/waiting-list/{$entryB->id}", ['action' => 'wait'])->assertJsonPath('data.status', 'waiting');

        // Enrolling the child closes their entry.
        $this->enrollTherapy($a, $speech, $therapist, $branch);
        app(WaitingListService::class)->enrolled($a->enrollments()->sole());
        $this->assertSame('enrolled', WaitingListEntry::where('patient_id', $a->id)->sole()->status);
        $this->assertCount(2, $this->getJson('/api/v1/waiting-list')->json('data'));
    }

    public function test_view_only_staff_cannot_change_the_list(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->userWithRole(Role::Therapist, $branch));
        $this->postJson('/api/v1/waiting-list', ['patient_id' => Patient::factory()->create()->id, 'branch_id' => $branch->id, 'type' => 'training', 'preferred_time' => 'any', 'priority' => 'normal'])
            ->assertForbidden();
    }
}
