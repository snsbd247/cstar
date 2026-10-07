<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Sprint 16 security hardening (Plan §২১): checks that guard the whole API, not one feature. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** Routes anyone may call without signing in. Adding one here must be a deliberate decision. */
    private const PUBLIC_API = ['POST api/v1/auth/login', 'POST api/v1/auth/forgot', 'POST api/v1/auth/reset'];

    public function test_every_api_route_requires_sign_in_except_the_public_list(): void
    {
        $open = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/'))
            ->reject(fn ($r) => in_array('auth:sanctum', $r->gatherMiddleware(), true))
            ->map(fn ($r) => implode('|', array_diff($r->methods(), ['HEAD'])).' '.$r->uri())
            ->values()->all();

        $this->assertSame(self::PUBLIC_API, $open, 'API routes reachable without signing in: '.implode(', ', $open));
    }

    public function test_website_contact_details_cannot_break_out_of_the_structured_data_script(): void
    {
        app(SiteSettings::class)->update(['address' => '</script><script>alert(1)</script>']);

        $this->get('/')->assertOk()->assertDontSee('<script>alert(1)', false);
    }

    public function test_accountant_can_load_services_for_the_package_form(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->userWithRole(Role::Accountant, $branch))->getJson('/api/v1/lookups/bookable-services')->assertOk();
        $this->actingAs($this->userWithRole(Role::Trainer, $branch))->getJson('/api/v1/lookups/bookable-services')->assertForbidden();
    }

    public function test_signed_out_requests_get_401_and_no_data(): void
    {
        Patient::factory()->create();
        foreach (['/api/v1/patients', '/api/v1/audit-logs', '/api/v1/settings', '/api/v1/accounts/chart', '/api/v1/portal/children', '/api/v1/settings/backups'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_parents_and_inactive_staff_cannot_reach_staff_data(): void
    {
        $branch = Branch::factory()->create();
        $parent = $this->userWithRole(Role::Parent, $branch);
        foreach (['/api/v1/patients', '/api/v1/invoices', '/api/v1/audit-logs', '/api/v1/records/documents', '/api/v1/hr/employees'] as $url) {
            $this->actingAs($parent)->getJson($url)->assertForbidden();
        }

        $staff = $this->userWithRole(Role::Receptionist, $branch);
        $staff->update(['status' => UserStatus::Inactive]);
        $this->actingAs($staff->fresh())->getJson('/api/v1/patients')->assertForbidden();
    }

    public function test_security_headers_on_api_and_website(): void
    {
        $api = $this->actingAs($this->userWithRole(Role::SuperAdmin))->getJson('/api/v1/auth/me')->assertOk();
        $api->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $api->headers->get('Cache-Control'));

        $site = $this->get('/')->assertOk();
        $site->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Permissions-Policy');
        $this->assertNull($site->headers->get('Strict-Transport-Security'), 'HSTS only over HTTPS');
    }
}
