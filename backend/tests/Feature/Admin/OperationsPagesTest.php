<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffLeave;
use App\Models\Therapist;
use App\Models\TherapistLeave;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Billing & Payments, Packages, Accounts, Staff and Branches menu pages added in Sprint 16. */
class OperationsPagesTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $reception;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['code' => 'HQ']);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
    }

    public function test_billing_pages_show_receipts_refunds_allocations_and_discounts(): void
    {
        $child = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $invoiceId = $this->actingAs($this->accountant)->postJson('/api/v1/invoices', [
            'patient_id' => $child->id, 'branch_id' => $this->branch->id, 'issue' => true, 'discount_reason' => 'Sibling concession',
            'items' => [['item_type' => 'other', 'description' => 'Assessment', 'unit_price' => 1000, 'discount' => 100]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->reception)->postJson("/api/v1/patients/{$child->id}/payments", ['amount' => 1500, 'method' => 'cash', 'branch_id' => $this->branch->id])->assertCreated();
        $this->actingAs($this->accountant)->postJson("/api/v1/patients/{$child->id}/refunds", ['amount' => 200, 'method' => 'cash', 'branch_id' => $this->branch->id, 'reason' => 'Overpaid'])
            ->assertCreated();

        $kpis = $this->getJson('/api/v1/billing/dashboard')->assertOk()->json('data.kpis');
        $this->assertEquals(1300, $kpis['collected_today']);
        $this->assertEquals(0, $kpis['outstanding']);
        $this->assertEquals(400, $kpis['advances_held']);
        $this->assertEquals(100, $kpis['discounts_month']);

        $this->assertEquals(1500, $this->getJson('/api/v1/billing/payment-list')->json('total'));
        $this->assertEquals(200, $this->getJson('/api/v1/billing/payment-list?type=refund')->json('total'));
        $allocation = $this->getJson('/api/v1/billing/allocations')->assertOk()->json('data.0');
        $this->assertEquals(900, $allocation['amount']);
        $this->assertSame($invoiceId, $allocation['invoice']['id']);
        $discount = $this->getJson('/api/v1/billing/discounts')->assertOk()->json('data.0');
        $this->assertSame('Sibling concession', $discount['reason']);
        $this->assertEquals(10, $discount['percent']);

        // A receptionist sees only the money they handled themselves.
        $this->actingAs($this->reception);
        $this->assertSame(1, $this->getJson('/api/v1/billing/payment-list')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/billing/payment-list?type=refund')->json('meta.total'));
        $this->getJson('/api/v1/package-usage')->assertOk();
    }

    public function test_leave_for_a_therapist_also_blocks_their_booking_days(): void
    {
        $speech = Service::factory()->create();
        $employeeId = $this->actingAs($this->accountant)->postJson('/api/v1/hr/employees', [
            'name' => 'Imran Hossain', 'branch_id' => $this->branch->id, 'joining_date' => '2025-01-01', 'employment_type' => 'full_time',
            'pay_type' => 'fixed', 'department' => 'therapist', 'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');
        $therapist = $this->therapistFor($speech, branch: $this->branch);
        $therapist->update(['employee_id' => $employeeId]);
        $saturday = Carbon::parse('next saturday');

        $res = $this->postJson('/api/v1/staff/leaves', ['employee_id' => $employeeId, 'type' => 'sick', 'start_date' => $saturday->toDateString(), 'end_date' => $saturday->copy()->addDays(6)->toDateString(), 'reason' => 'Fever'])
            ->assertCreated();
        $this->assertSame(6, $res->json('data.days')); // Friday is not counted
        $leave = StaffLeave::firstOrFail();
        $this->assertTrue(TherapistLeave::whereKey($leave->therapist_leave_id)->where('therapist_id', $therapist->id)->exists());

        $this->postJson('/api/v1/staff/leaves', ['employee_id' => $employeeId, 'type' => 'casual', 'start_date' => $saturday->copy()->addDay()->toDateString(), 'end_date' => $saturday->copy()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $rows = $this->getJson('/api/v1/staff/leaves?from='.$saturday->toDateString())->assertOk()->json('data');
        $this->assertCount(1, $rows); // the linked therapist leave is not listed twice
        $this->assertSame('Sick', $rows[0]['type_label']);

        $this->deleteJson("/api/v1/staff/leaves/{$leave->id}")->assertOk();
        $this->assertSame(0, TherapistLeave::count());
        $this->actingAs($this->reception)->postJson('/api/v1/staff/leaves', ['employee_id' => $employeeId, 'type' => 'sick', 'start_date' => $saturday->toDateString(), 'end_date' => $saturday->toDateString()])
            ->assertForbidden();
    }

    public function test_staff_accounts_and_branch_lists(): void
    {
        $admin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->actingAs($this->accountant);
        $employeeId = $this->postJson('/api/v1/hr/employees', [
            'name' => 'Front Desk', 'branch_id' => $this->branch->id, 'joining_date' => '2025-01-01', 'employment_type' => 'full_time',
            'pay_type' => 'fixed', 'department' => 'admin', 'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/hr/employees/{$employeeId}/salary-structures", ['effective_from' => '2025-01-01', 'basic' => 10000, 'house_rent' => 4000])->assertCreated();
        $row = collect($this->getJson('/api/v1/hr/salary-structures')->assertOk()->json('data'))->firstWhere('employee.id', $employeeId);
        $this->assertEquals(14000, $row['current']['total']);
        $this->getJson('/api/v1/hr/advances?status=open')->assertOk()->assertJsonPath('outstanding', 0);

        // Bank accounts: the cash box and main bank appear; a new bank account gets the next code in its group.
        $this->assertContains('1131', array_column($this->getJson('/api/v1/accounts/money-accounts')->assertOk()->json('data'), 'code'));
        $this->actingAs($this->reception)->postJson('/api/v1/accounts/money-accounts', ['kind' => 'bank', 'name' => 'DBBL — Current 4521'])->assertForbidden();
        $this->actingAs($this->accountant)->postJson('/api/v1/accounts/money-accounts', ['kind' => 'bank', 'name' => 'DBBL — Current 4521'])
            ->assertCreated()->assertJsonPath('data.code', '1132');
        $this->assertSame('bank', Account::where('code', '1132')->value('subtype'));
        $this->getJson('/api/v1/accounts/vendor-bills?status=overdue')->assertOk()->assertJsonPath('summary.owed', 0);

        // Rooms, services and staff by branch.
        $this->actingAs($admin);
        $roomId = $this->postJson('/api/v1/rooms', ['branch_id' => $this->branch->id, 'name' => 'Therapy room 1', 'type' => 'therapy', 'capacity' => 3])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/rooms', ['branch_id' => $this->branch->id, 'name' => 'Therapy room 1', 'type' => 'therapy'])->assertUnprocessable();
        $this->putJson("/api/v1/rooms/{$roomId}", ['branch_id' => $this->branch->id, 'name' => 'Therapy room 1', 'type' => 'therapy', 'is_active' => false])->assertOk();
        $this->assertFalse($this->getJson('/api/v1/rooms')->json('data.0.is_active'));
        $this->postJson('/api/v1/rooms', ['branch_id' => Branch::factory()->create()->id, 'name' => 'Elsewhere', 'type' => 'other'])->assertForbidden();
        $this->getJson('/api/v1/branches-overview/services')->assertOk()->assertJsonPath('data.branches.0.branch.id', $this->branch->id);
        $staff = $this->getJson('/api/v1/branches-overview/staff')->assertOk()->json('data.0');
        $this->assertSame('Front Desk', $staff['employees']['admin'][0]['name']);
        $this->getJson('/api/v1/staff/assignments')->assertOk();
    }
}
