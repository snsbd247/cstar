<?php

namespace Tests\Feature\Website;

use App\Enums\Role;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\Therapist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->branch = Branch::factory()->create(['name' => 'Dhaka Branch']);
    }

    private function appointment(array $overrides = []): array
    {
        return [
            'parent_name' => 'Nusrat Jahan', 'child_name' => 'Ayan', 'child_age_years' => 5,
            'phone' => '01712345678', 'branch_id' => $this->branch->id, ...$overrides,
        ];
    }

    public function test_all_public_pages_render(): void
    {
        $service = Service::factory()->create(['slug' => 'speech-therapy', 'name' => 'Speech Therapy', 'show_on_website' => true]);
        $therapist = Therapist::factory()->create(['slug' => 'imran', 'name' => 'Imran', 'show_on_website' => true]);
        $therapist->services()->attach($service);

        foreach (['/', '/about', '/services', '/services/speech-therapy', '/therapists', '/therapists/imran', '/trainers',
            '/branches', '/gallery', '/faq', '/notices', '/contact', '/appointment'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/')->assertSee('Speech Therapy')->assertSee('Imran')->assertSee('Dhaka Branch');
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/services/speech-therapy'));
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /app')->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_hidden_content_never_reaches_the_website(): void
    {
        Therapist::factory()->create(['slug' => 'hidden', 'name' => 'Hidden Person', 'show_on_website' => false]);
        Testimonial::create(['name' => 'Draft Parent', 'content' => 'Draft quote', 'is_published' => false]);
        Faq::create(['question' => 'Unpublished question?', 'answer' => 'x', 'is_published' => false]);
        // Published but WITHOUT photo consent: must not appear.
        GalleryItem::create(['title' => 'No consent photo', 'image_path' => 'gallery/x.jpg', 'is_published' => true, 'consent_confirmed' => false]);

        $this->get('/therapists/hidden')->assertNotFound();
        $this->get('/')->assertDontSee('Draft quote')->assertDontSee('Unpublished question?');
        $this->get('/gallery')->assertDontSee('No consent photo');
    }

    public function test_appointment_request_is_stored_and_front_desk_is_notified(): void
    {
        $receptionist = $this->userWithRole(Role::Receptionist, $this->branch);
        $otherBranchReceptionist = $this->userWithRole(Role::Receptionist);
        $trainer = $this->userWithRole(Role::Trainer, $this->branch);

        // A real browser always sends the hidden honeypot field, empty.
        $this->post('/appointment', $this->appointment(['website' => '']))->assertRedirect(route('appointment.thanks'));

        $request = AppointmentRequest::firstOrFail();
        $this->assertSame('new', $request->status);
        $this->assertMatchesRegularExpression('/^REQ-\d{4}-\d{5}$/', $request->reference);
        $this->assertNull($request->patient_id); // a lead, not a patient yet

        $this->assertCount(1, $receptionist->notifications);
        $this->assertCount(0, $otherBranchReceptionist->notifications);
        $this->assertCount(0, $trainer->notifications);

        $this->followingRedirects()->post('/appointment', $this->appointment(['child_name' => 'Sara']))->assertSee('REQ-');
    }

    public function test_appointment_form_validation_and_honeypot(): void
    {
        $this->post('/appointment', $this->appointment(['phone' => '123']))->assertSessionHasErrors('phone');
        $this->post('/appointment', $this->appointment(['preferred_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('preferred_date');
        $this->post('/appointment', $this->appointment(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

        $this->assertSame(0, AppointmentRequest::count());
    }

    public function test_public_forms_are_rate_limited(): void
    {
        RateLimiter::clear('website-forms');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', ['name' => 'A', 'phone' => '01712345678', 'message' => 'Hello', 'website' => ''])->assertRedirect(route('contact'));
        }

        $this->post('/contact', ['name' => 'A', 'phone' => '01712345678', 'message' => 'Hello'])->assertTooManyRequests();
    }
}
