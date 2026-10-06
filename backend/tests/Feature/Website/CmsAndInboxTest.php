<?php

namespace Tests\Feature\Website;

use App\Enums\Role;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsAndInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_cms_managers_edit_website_settings(): void
    {
        $this->actingAs($this->userWithRole(Role::Receptionist))->putJson('/api/v1/cms/settings', ['phone' => '01700'])->assertForbidden();
        $this->actingAs($this->userWithRole(Role::BranchAdmin))->getJson('/api/v1/cms/settings')->assertForbidden();

        $this->actingAs($this->userWithRole(Role::SuperAdmin))
            ->putJson('/api/v1/cms/settings', ['phone' => '+880 1711-000000', 'stat_years' => '10+'])
            ->assertOk()->assertJsonPath('data.phone', '+880 1711-000000');

        $this->withoutVite()->get('/')->assertSee('+880 1711-000000')->assertSee('10+');
    }

    public function test_cms_content_crud_and_service_page_edit(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $service = Service::factory()->create(['slug' => 'ot', 'show_on_website' => true]);

        $id = $this->actingAs($admin)->postJson('/api/v1/cms/faqs', ['question' => 'Do you take walk-ins?', 'answer' => 'Yes.', 'is_published' => true])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->putJson("/api/v1/cms/faqs/{$id}", ['question' => 'Do you accept walk-ins?', 'answer' => 'Yes.', 'is_published' => true])->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/cms/faqs')->assertJsonPath('data.0.question', 'Do you accept walk-ins?');

        $this->actingAs($admin)->putJson("/api/v1/cms/services/{$service->id}", [
            'name' => 'Occupational Therapy', 'description' => 'Helps with daily skills.', 'show_on_website' => true,
        ])->assertOk();
        $this->withoutVite()->get('/services/ot')->assertSee('Helps with daily skills.');

        $this->actingAs($admin)->deleteJson("/api/v1/cms/faqs/{$id}")->assertNoContent();
    }

    public function test_gallery_photo_cannot_be_published_without_consent(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->post('/api/v1/cms/gallery', [
            'title' => 'Sports day', 'image' => UploadedFile::fake()->image('a.jpg'), 'is_published' => '1', 'consent_confirmed' => '0',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('consent_confirmed');

        $this->actingAs($admin)->post('/api/v1/cms/gallery', [
            'title' => 'Sports day', 'image' => UploadedFile::fake()->image('a.jpg'), 'is_published' => '1', 'consent_confirmed' => '1',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertCount(1, Storage::disk('public')->allFiles('gallery'));
    }

    public function test_receptionist_works_own_branch_requests_and_converts_after_registration(): void
    {
        $branch = Branch::factory()->create();
        $mine = AppointmentRequest::create(['reference' => 'REQ-1', 'branch_id' => $branch->id, 'parent_name' => 'P', 'child_name' => 'C', 'phone' => '01712345678']);
        $other = AppointmentRequest::create(['reference' => 'REQ-2', 'branch_id' => Branch::factory()->create()->id, 'parent_name' => 'Q', 'child_name' => 'D', 'phone' => '01812345678']);
        $receptionist = $this->userWithRole(Role::Receptionist, $branch);

        $this->actingAs($receptionist)->getJson('/api/v1/appointment-requests')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', 'REQ-1');
        $this->actingAs($receptionist)->putJson("/api/v1/appointment-requests/{$other->id}", ['status' => 'contacted'])->assertForbidden();

        $this->actingAs($receptionist)->putJson("/api/v1/appointment-requests/{$mine->id}", ['status' => 'converted'])
            ->assertUnprocessable()->assertJsonValidationErrors('patient_id');

        $patient = Patient::factory()->create(['home_branch_id' => $branch->id]);
        $this->actingAs($receptionist)->putJson("/api/v1/appointment-requests/{$mine->id}", ['status' => 'converted', 'patient_id' => $patient->id])->assertOk();
        $this->assertSame($receptionist->id, $mine->fresh()->handled_by);

        $this->actingAs($this->userWithRole(Role::Trainer, $branch))->getJson('/api/v1/appointment-requests')->assertForbidden();
    }

    public function test_notifications_bell(): void
    {
        $branch = Branch::factory()->create();
        $receptionist = $this->userWithRole(Role::Receptionist, $branch);
        $this->withoutVite()->post('/appointment', ['parent_name' => 'P', 'child_name' => 'C', 'phone' => '01712345678', 'branch_id' => $branch->id]);

        $response = $this->actingAs($receptionist)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 1);
        $this->actingAs($receptionist)->postJson('/api/v1/notifications/'.$response->json('data.0.id').'/read')->assertOk();
        $this->actingAs($receptionist)->getJson('/api/v1/notifications')->assertJsonPath('unread', 0);
    }
}
