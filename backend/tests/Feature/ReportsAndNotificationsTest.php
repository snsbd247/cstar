<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use App\Services\AccountMap;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class ReportsAndNotificationsTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $reception;

    private User $accountant;

    private User $branchAdmin;

    private User $parent;

    private Patient $ayan;

    private Service $speech;

    private Therapist $imran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->seed([ChartOfAccountsSeeder::class, ExpenseCategorySeeder::class]);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
        $this->branchAdmin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech']);
        $this->imran = $this->therapistFor($this->speech, null, $this->branch);
        $this->ayan = Patient::factory()->create(['home_branch_id' => $this->branch->id, 'name' => 'Ayan']);
        $this->parent = $this->userWithRole(Role::Parent, $this->branch);
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $guardian->patients()->attach($this->ayan, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);
    }

    public function test_report_catalog_follows_permissions(): void
    {
        $reception = collect($this->actingAs($this->reception)->getJson('/api/v1/reports')->assertOk()->json('data'))->pluck('key');
        $this->assertTrue($reception->contains('appointments'));
        $this->assertFalse($reception->contains('revenue'));
        $this->actingAs($this->reception)->getJson('/api/v1/reports/revenue')->assertForbidden();

        $accountant = collect($this->actingAs($this->accountant)->getJson('/api/v1/reports')->json('data'))->pluck('key');
        $this->assertTrue($accountant->contains('revenue'));
        $this->actingAs($this->userWithRole(Role::Therapist, $this->branch))->getJson('/api/v1/reports')->assertForbidden();
    }

    public function test_every_report_runs_and_exports(): void
    {
        $this->actingAs($this->accountant)->postJson('/api/v1/invoices', [
            'patient_id' => $this->ayan->id, 'branch_id' => $this->branch->id, 'issue' => true, 'items' => [['item_type' => 'training_fee', 'unit_price' => 3000]],
        ])->assertCreated();
        $this->actingAs($this->accountant)->postJson("/api/v1/patients/{$this->ayan->id}/payments", ['amount' => 1000, 'method' => 'cash', 'branch_id' => $this->branch->id]);

        foreach (collect($this->actingAs($this->branchAdmin)->getJson('/api/v1/reports')->json('data'))->pluck('key') as $key) {
            $this->actingAs($this->branchAdmin)->getJson("/api/v1/reports/{$key}")->assertOk()->assertJsonStructure(['data' => ['columns', 'rows', 'title']]);
        }

        $this->actingAs($this->accountant)->getJson('/api/v1/reports/revenue')->assertOk()->assertJsonPath('data.totals.training', 3000);
        $this->actingAs($this->accountant)->getJson('/api/v1/reports/due-aging')->assertOk()->assertJsonPath('data.totals.total', 2000);

        $pdf = $this->actingAs($this->accountant)->get('/api/v1/reports/collection?format=pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $csv = $this->actingAs($this->accountant)->get('/api/v1/reports/collection?format=csv');
        $csv->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBFDate", $csv->streamedContent());
        $this->actingAs($this->accountant)->getJson('/api/v1/reports/nope')->assertNotFound();
    }

    public function test_reports_are_limited_to_the_users_branches(): void
    {
        $other = Branch::factory()->create();
        $this->actingAs($this->reception)->getJson("/api/v1/reports/appointments?branch_id={$other->id}")->assertForbidden();
    }

    public function test_parents_hear_about_appointments_bills_and_payments(): void
    {
        $this->imran->schedules()->create(['branch_id' => $this->branch->id, 'weekday' => today()->addDays(2)->dayOfWeek, 'start_time' => '09:00', 'end_time' => '18:00', 'slot_minutes' => 45]);
        $this->actingAs($this->reception)->postJson('/api/v1/appointments', [
            'patient_id' => $this->ayan->id, 'service_id' => $this->speech->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today()->addDays(2)->toDateString(), 'start_time' => '10:00',
        ])->assertCreated();
        $this->actingAs($this->reception)->postJson('/api/v1/invoices', [
            'patient_id' => $this->ayan->id, 'branch_id' => $this->branch->id, 'issue' => true, 'items' => [['item_type' => 'admission', 'unit_price' => 2000]],
        ])->assertCreated();
        $this->actingAs($this->reception)->postJson("/api/v1/patients/{$this->ayan->id}/payments", ['amount' => 2000, 'method' => 'cash', 'branch_id' => $this->branch->id]);

        $kinds = collect($this->actingAs($this->parent)->getJson('/api/v1/notifications')->assertOk()->json('data'))->pluck('kind');
        $this->assertEqualsCanonicalizing(['appointment.booked', 'invoice.issued', 'payment.received'], $kinds->all());
        $this->assertContains('নতুন অ্যাপয়েন্টমেন্ট', $this->parent->notifications()->get()->pluck('data.title')->all());
    }

    public function test_tomorrows_appointments_send_a_reminder(): void
    {
        Appointment::create([
            'appointment_code' => 'APT-R-1', 'patient_id' => $this->ayan->id, 'service_id' => $this->speech->id, 'therapist_id' => $this->imran->id,
            'branch_id' => $this->branch->id, 'date' => today()->addDay()->toDateString(), 'start_time' => '16:00:00', 'end_time' => '16:45:00',
            'type' => 'therapy', 'status' => 'confirmed',
        ]);
        $this->artisan('cstar:reminders --type=parents')->assertSuccessful();
        $this->assertSame('appointment.reminder', $this->parent->notifications()->first()->data['kind']);
        $this->assertStringContainsString('বিকাল ৪:০০', $this->parent->notifications()->first()->data['body']);
    }

    public function test_approvers_are_told_when_a_voucher_waits(): void
    {
        $this->actingAs($this->accountant)->postJson('/api/v1/accounts/expenses', [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'amount' => 20000,
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'paid_from_account_id' => app(AccountMap::class)->cashFor($this->branch->id)->id,
        ])->assertCreated();

        $this->assertSame(1, $this->branchAdmin->notifications()->count());
        $this->assertSame(0, $this->accountant->notifications()->count()); // never the preparer
    }

    public function test_announcement_reaches_the_right_audience(): void
    {
        $send = fn (array $body) => $this->actingAs($this->branchAdmin)->postJson('/api/v1/announcements', [
            'title' => 'Center closed Thursday', 'body' => 'বৃহস্পতিবার কেন্দ্র বন্ধ থাকবে।', ...$body,
        ]);
        $send(['audience' => 'parents', 'preview' => true])->assertOk()->assertJsonPath('data.recipients', 1);
        $send(['audience' => 'parents'])->assertCreated()->assertJsonPath('data.recipients_count', 1);
        $this->assertSame('announcement', $this->parent->notifications()->first()->data['kind']);

        $staff = $send(['audience' => 'staff', 'preview' => true])->json('data.recipients');
        $this->assertGreaterThanOrEqual(3, $staff);
        $this->actingAs($this->reception)->postJson('/api/v1/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'staff'])->assertForbidden();
    }

    public function test_dashboard_has_charts_and_actions(): void
    {
        $this->actingAs($this->accountant)->postJson('/api/v1/invoices', [
            'patient_id' => $this->ayan->id, 'branch_id' => $this->branch->id, 'issue' => true, 'items' => [['item_type' => 'other', 'unit_price' => 500]],
        ]);
        $res = $this->actingAs($this->branchAdmin)->getJson('/api/v1/dashboard')->assertOk();
        $res->assertJsonCount(6, 'charts.registrations')->assertJsonCount(6, 'charts.revenue');
        $this->assertSame('due', $res->json('actions.0.kind'));
        $this->actingAs($this->reception)->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('charts.revenue');
    }
}
