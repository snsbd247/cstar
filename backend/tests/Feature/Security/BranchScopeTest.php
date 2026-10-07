<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 21: a branch admin works only on their own branch's children, therapists and lists. */
class BranchScopeTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_branch_admin_cannot_touch_another_branch(): void
    {
        $mine = Branch::factory()->create();
        $other = Branch::factory()->create();
        $speech = Service::factory()->create();
        $theirs = $this->therapistFor($speech, branch: $other);
        $ours = $this->therapistFor($speech, branch: $mine);
        $child = Patient::factory()->create(['home_branch_id' => $other->id]);
        $entry = WaitingListEntry::create(['patient_id' => $child->id, 'branch_id' => $other->id, 'type' => 'training', 'preferred_time' => 'any', 'priority' => 'normal', 'status' => 'waiting']);
        $this->actingAs($this->userWithRole(Role::BranchAdmin, $mine));

        $this->putJson("/api/v1/therapists/{$theirs->id}", [
            'name' => 'Moved', 'therapist_type' => $theirs->therapist_type->value, 'primary_branch_id' => $mine->id, 'status' => 'active', 'service_ids' => [$speech->id],
        ])->assertForbidden();
        $this->postJson("/api/v1/therapists/{$theirs->id}/leaves", ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()])->assertForbidden();
        $this->postJson("/api/v1/therapists/{$ours->id}/leaves", ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()])->assertCreated();

        $this->getJson("/api/v1/patients/{$child->id}")->assertForbidden();
        $this->getJson("/api/v1/patients/{$child->id}/progress-chart")->assertForbidden();
        $this->putJson("/api/v1/waiting-list/{$entry->id}", ['action' => 'offer'])->assertForbidden();
    }
}
