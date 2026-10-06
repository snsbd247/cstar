<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Website / CMS, Notifications, Branch Access and the Sprint 16 reports. */
class WebsiteNotificationsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_pages_sections_and_seo_change_the_public_site(): void
    {
        $this->actingAs($this->userWithRole(Role::SuperAdmin));
        $pages = collect($this->getJson('/api/v1/cms/structure')->assertOk()->json('data.pages'))
            ->map(fn ($p) => [...$p, 'in_menu' => $p['key'] === 'faq' ? false : $p['in_menu'], 'title' => $p['key'] === 'home' ? 'Speech therapy in Dhaka | C-STAR' : $p['title']])->all();
        $this->putJson('/api/v1/cms/pages', ['pages' => $pages])->assertOk();

        $sections = collect($this->getJson('/api/v1/cms/structure')->json('data.sections'))->map(fn ($s) => [...$s, 'visible' => $s['key'] !== 'steps'])->reverse()->values()->all();
        $this->putJson('/api/v1/cms/home-sections', ['sections' => $sections])->assertOk();
        $this->putJson('/api/v1/cms/home-sections', ['sections' => array_slice($sections, 1)])->assertUnprocessable();

        $this->putJson('/api/v1/cms/seo', ['seo_noindex' => '1', 'google_analytics_id' => 'UA-123'])->assertUnprocessable()->assertJsonValidationErrors('google_analytics_id');
        $this->putJson('/api/v1/cms/seo', ['seo_noindex' => '1', 'google_site_verification' => 'abc123_X'])->assertOk();

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Speech therapy in Dhaka | C-STAR</title>', $home);
        $this->assertStringNotContainsString('Four simple steps', $home);
        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $home);
        $this->assertStringContainsString('content="abc123_X"', $home);
        // Hidden from the top menu (the footer quick links keep it).
        $about = $this->get('/about')->getContent();
        $header = substr($about, strpos($about, '<header'), strpos($about, '</header>') - strpos($about, '<header'));
        $this->assertStringNotContainsString(route('faq').'"', $header);
        $this->assertStringContainsString(route('contact').'"', $header);
        $this->assertStringContainsString("Disallow: /\n", $this->get('/robots.txt')->getContent());

        // Reversed order: the notice board (last by default) now comes before the hero.
        $this->assertSame('notices', $this->getJson('/api/v1/cms/structure')->json('data.sections.0.key'));
        $this->getJson('/api/v1/cms/dashboard')->assertOk()->assertJsonPath('data.seo.noindex', true);
    }

    public function test_templates_change_messages_and_logs_show_them(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $branch = Branch::factory()->create();
        $admin = $this->userWithRole(Role::SuperAdmin);
        $child = Patient::factory()->create(['home_branch_id' => $branch->id, 'name' => 'Sara Khan']);
        $parent = $this->userWithRole(Role::Parent, $branch);
        $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
        $child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);

        $this->actingAs($admin)->putJson('/api/v1/notification-templates/payment.received', ['title' => 'ধন্যবাদ', 'body' => '{child}-এর জন্য {amount} — {bad}'])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->putJson('/api/v1/notification-templates/payment.received', ['title' => 'ধন্যবাদ', 'body' => '{child}-এর জন্য {amount} পেয়েছি ({receipt_no})'])
            ->assertOk()->assertJsonPath('data.title', 'ধন্যবাদ');

        app(NotificationService::class)->parentsTemplate($child, 'payment.received', ['amount' => '৳৫০০', 'receipt_no' => 'RCP-1']);
        $mine = $this->actingAs($parent->fresh())->getJson('/api/v1/notifications/center')->assertOk()->json('data.0');
        $this->assertSame('ধন্যবাদ', $mine['title']);
        $this->assertSame('Sara Khan-এর জন্য ৳৫০০ পেয়েছি (RCP-1)', $mine['body']);

        $log = $this->actingAs($admin)->getJson('/api/v1/notification-logs?audience=parents&kind=payment.received')->assertOk()->json('data.0');
        $this->assertSame('parent', $log['to']['type']);
        $this->assertNull($log['read_at']);

        // Back to the built-in wording.
        $this->putJson('/api/v1/notification-templates/payment.received', ['title' => '', 'body' => ''])->assertOk();
        $this->assertSame('পেমেন্ট গ্রহণ করা হয়েছে', app(\App\Services\NotificationTemplates::class)->render('payment.received', ['amount' => '1', 'receipt_no' => 'x'])['title']);
        $this->actingAs($this->userWithRole(Role::BranchAdmin, $branch))->putJson('/api/v1/notification-templates/payment.received', ['title' => 'x'])->assertForbidden();
    }

    public function test_branch_access_is_limited_to_own_branches(): void
    {
        [$a, $b, $c] = Branch::factory()->count(3)->create();
        $branchAdmin = $this->userWithRole(Role::BranchAdmin, $a, $b);
        $staff = $this->userWithRole(Role::Receptionist, $a, $c);

        $row = collect($this->actingAs($branchAdmin)->getJson('/api/v1/branch-access')->assertOk()->json('users'))->firstWhere('id', $staff->id);
        $this->assertNotNull($row);
        $this->putJson("/api/v1/branch-access/{$staff->id}", ['branch_ids' => [$a->id, $c->id]])->assertUnprocessable();
        $this->putJson("/api/v1/branch-access/{$staff->id}", ['branch_ids' => [], 'primary_id' => null])->assertUnprocessable();

        // Granting branch B keeps branch C, which the branch admin cannot see or change.
        $this->putJson("/api/v1/branch-access/{$staff->id}", ['branch_ids' => [$b->id], 'primary_id' => $b->id])->assertOk();
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $staff->branches()->pluck('branches.id')->all());
        $this->assertSame($b->id, $staff->branches()->wherePivot('is_primary', true)->value('branches.id'));

        $this->actingAs($this->userWithRole(Role::Receptionist, $a))->putJson("/api/v1/branch-access/{$staff->id}", ['branch_ids' => [$a->id]])->assertForbidden();
    }

    public function test_new_reports_run_and_export(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $branch = Branch::factory()->create();
        Patient::factory()->count(2)->create(['home_branch_id' => $branch->id, 'registration_date' => today()]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);
        $this->actingAs($admin->fresh());

        foreach (['patients', 'enrollments', 'assessments', 'staff'] as $key) {
            $this->getJson("/api/v1/reports/{$key}?from=".today()->startOfMonth()->toDateString().'&to='.today()->toDateString())->assertOk()->assertJsonStructure(['data' => ['columns', 'rows']]);
        }
        $this->assertSame(2, $this->getJson('/api/v1/reports/patients')->json('data.rows.0.new'));
        $this->get('/api/v1/reports/staff?format=csv')->assertOk();
        $this->assertContains('patients', array_column($this->getJson('/api/v1/reports')->json('data'), 'key'));
    }
}
