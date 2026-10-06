<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\EmployeeAdvance;
use App\Models\JournalLine;
use App\Models\Patient;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\AccountMap;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private User $branchAdmin;

    private Service $speech;

    private Account $cash;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['code' => 'HQ']);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
        $this->branchAdmin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->speech = Service::factory()->create(['name' => 'Speech Therapy', 'slug' => 'speech']);
        $this->cash = app(AccountMap::class)->cashFor($this->branch->id);
        $this->month = today()->subMonth()->format('Y-m');
    }

    private function employee(array $data): array
    {
        return $this->actingAs($this->accountant)->postJson('/api/v1/hr/employees', [
            'branch_id' => $this->branch->id, 'joining_date' => '2025-01-01', 'employment_type' => 'full_time',
            'pay_type' => 'fixed', 'department' => 'admin', 'payment_method' => 'cash', ...$data,
        ])->assertCreated()->json('data');
    }

    private function structure(int $employeeId, array $data): void
    {
        $this->actingAs($this->accountant)->postJson("/api/v1/hr/employees/{$employeeId}/salary-structures", ['effective_from' => '2025-01-01', ...$data])->assertCreated();
    }

    /** Finalized sessions for a therapist in the payroll month. */
    private function sessions(Therapist $therapist, int $count): void
    {
        $patient = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $start = Carbon::createFromFormat('Y-m-d', $this->month.'-01');
        for ($i = 0; $i < $count; $i++) {
            $a = Appointment::create([
                'appointment_code' => 'APT-P-'.uniqid(), 'patient_id' => $patient->id, 'service_id' => $this->speech->id, 'therapist_id' => $therapist->id,
                'branch_id' => $this->branch->id, 'date' => $start->copy()->addDays($i)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '10:45:00',
                'type' => 'therapy', 'status' => 'completed',
            ]);
            TherapySession::create([
                'appointment_id' => $a->id, 'patient_id' => $patient->id, 'therapist_id' => $therapist->id, 'service_id' => $this->speech->id,
                'branch_id' => $this->branch->id, 'date' => $a->date, 'status' => 'final', 'finalized_at' => now(),
            ]);
        }
    }

    private function makeRun(string $type = 'salary', array $extra = [])
    {
        return $this->actingAs($this->accountant)->postJson('/api/v1/payroll/runs', ['month' => $this->month, 'branch_id' => $this->branch->id, 'type' => $type, ...$extra]);
    }

    private function balance(string $code): float
    {
        $a = Account::where('code', $code)->firstOrFail();
        $net = (float) JournalLine::where('account_id', $a->id)->sum('debit') - (float) JournalLine::where('account_id', $a->id)->sum('credit');

        return round($a->normal_balance === 'debit' ? $net : -$net, 2);
    }

    public function test_fixed_salary_with_mid_month_joiner_posts_by_department(): void
    {
        $rina = $this->employee(['name' => 'Rina (reception)']);
        $this->structure($rina['id'], ['basic' => 15000, 'house_rent' => 5000, 'conveyance' => 1000]);
        $joinDay = Carbon::createFromFormat('Y-m-d', $this->month.'-16');
        $aya = $this->employee(['name' => 'Aya', 'department' => 'support', 'joining_date' => $joinDay->toDateString()]);
        $this->structure($aya['id'], ['basic' => 9000, 'effective_from' => $joinDay->toDateString()]);

        $runId = $this->makeRun()->assertCreated()->json('data.id');
        $items = $this->actingAs($this->accountant)->getJson("/api/v1/payroll/runs/{$runId}")->assertOk()->json('data.items');
        $byName = collect($items)->keyBy(fn ($i) => $i['employee']['name']);
        $this->assertEquals(21000, $byName['Rina (reception)']['net_pay']);
        $days = $joinDay->daysInMonth - 15;
        $this->assertEqualsWithDelta(9000 * $days / $joinDay->daysInMonth, $byName['Aya']['net_pay'], 1);

        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk()->assertJsonPath('data.status', 'posted');
        $this->assertEquals(21000, $this->balance('5130'));
        $this->assertEqualsWithDelta($byName['Aya']['net_pay'], $this->balance('5140'), 0.01);
        $this->assertEqualsWithDelta(21000 + $byName['Aya']['net_pay'], $this->balance('2400'), 0.01);
    }

    public function test_session_pay_comes_from_finalized_sessions(): void
    {
        $imranUser = $this->userWithRole(Role::Therapist, $this->branch);
        $imran = $this->therapistFor($this->speech, $imranUser, $this->branch);
        $farhana = $this->therapistFor($this->speech, null, $this->branch);
        $this->sessions($imran, 10);
        $this->sessions($farhana, 4);

        // Imran: mixed — salary covers 8 sessions, extras at ৳300. Farhana: per session ৳500.
        $e1 = $this->employee(['name' => 'Imran', 'department' => 'therapist', 'pay_type' => 'mixed', 'therapist_id' => $imran->id, 'user_id' => $imranUser->id]);
        $this->structure($e1['id'], ['basic' => 30000, 'included_sessions' => 8]);
        $this->actingAs($this->accountant)->postJson("/api/v1/hr/employees/{$e1['id']}/session-rates", ['service_id' => $this->speech->id, 'rate' => 300, 'effective_from' => '2025-01-01'])->assertCreated();
        $e2 = $this->employee(['name' => 'Farhana', 'department' => 'therapist', 'pay_type' => 'per_session', 'employment_type' => 'visiting', 'therapist_id' => $farhana->id]);
        $this->structure($e2['id'], ['basic' => 0]);
        $this->actingAs($this->accountant)->postJson("/api/v1/hr/employees/{$e2['id']}/session-rates", ['rate' => 500, 'effective_from' => '2025-01-01'])->assertCreated();

        $runId = $this->makeRun()->json('data.id');
        $items = collect($this->actingAs($this->accountant)->getJson("/api/v1/payroll/runs/{$runId}")->json('data.items'))->keyBy(fn ($i) => $i['employee']['name']);
        $this->assertEquals(10, $items['Imran']['session_count']);
        $this->assertEquals(600, $items['Imran']['session_pay']);
        $this->assertEquals(30600, $items['Imran']['net_pay']);
        $this->assertEquals(2000, $items['Farhana']['session_pay']);

        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk();
        $this->assertEquals(30000, $this->balance('5110'));
        $this->assertEquals(2600, $this->balance('5200'));
    }

    public function test_advance_is_recovered_in_instalments_and_restored_on_reopen(): void
    {
        $e = $this->employee(['name' => 'Karim', 'department' => 'support']);
        $this->structure($e['id'], ['basic' => 10000]);
        $this->actingAs($this->accountant)->postJson("/api/v1/hr/employees/{$e['id']}/advances", [
            'date' => today()->toDateString(), 'amount' => 6000, 'installment' => 2000, 'paid_from_account_id' => $this->cash->id, 'reason' => 'Medical',
        ])->assertCreated();
        $this->assertEquals(6000, $this->balance('1300'));

        $runId = $this->makeRun()->json('data.id');
        $item = PayrollItem::where('payroll_run_id', $runId)->firstOrFail();
        $this->assertEquals(2000, (float) $item->advance_deduction);
        $this->assertEquals(8000, (float) $item->net_pay);

        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk();
        $this->assertEquals(4000, (float) EmployeeAdvance::firstOrFail()->balance);
        $this->assertEquals(4000, $this->balance('1300'));

        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/reopen", ['reason' => 'Wrong absence'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertEquals(6000, (float) EmployeeAdvance::firstOrFail()->balance);
        $this->assertEquals(6000, $this->balance('1300'));
    }

    public function test_adjustments_second_approver_and_payment(): void
    {
        $e = $this->employee(['name' => 'Rina']);
        $this->structure($e['id'], ['basic' => 20000]);
        $runId = $this->makeRun()->json('data.id');
        $item = PayrollItem::where('payroll_run_id', $runId)->firstOrFail();

        $this->actingAs($this->accountant)->putJson("/api/v1/payroll/items/{$item->id}", ['absence_deduction' => 1000, 'tax' => 500])
            ->assertOk()->assertJsonPath('data.gross', '19000.00')->assertJsonPath('data.net_pay', '18500.00');
        $this->actingAs($this->accountant)->putJson("/api/v1/payroll/items/{$item->id}", ['other_deduction' => 50000])->assertJsonValidationErrors('net_pay');

        $this->actingAs($this->accountant)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertJsonValidationErrors('payroll');
        $this->actingAs($this->userWithRole(Role::Receptionist, $this->branch))->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertForbidden();
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk();
        $this->actingAs($this->accountant)->putJson("/api/v1/payroll/items/{$item->id}", ['tax' => 0])->assertJsonValidationErrors('payroll');
        $this->assertEquals(500, $this->balance('2500'));

        $this->actingAs($this->accountant)->postJson("/api/v1/payroll/runs/{$runId}/pay", ['paid_from_account_id' => $this->account('1131')->id])
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertEquals(0, $this->balance('2400'));
        $this->assertEquals(-18500, $this->balance('1131'));
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_bonus_run_pays_one_basic(): void
    {
        $e = $this->employee(['name' => 'Rina']);
        $this->structure($e['id'], ['basic' => 15000, 'house_rent' => 5000]);
        $runId = $this->makeRun('bonus', ['title' => 'Eid-ul-Fitr bonus'])->assertCreated()->json('data.id');
        $this->assertEquals(15000, (float) PayrollRun::findOrFail($runId)->total_net);
        $this->makeRun('bonus', ['title' => 'Eid-ul-Fitr bonus'])->assertJsonValidationErrors('month');

        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk();
        $this->assertEquals(15000, $this->balance('5150'));
    }

    public function test_staff_see_only_their_own_payslips(): void
    {
        $me = $this->userWithRole(Role::Receptionist, $this->branch);
        $other = $this->userWithRole(Role::Trainer, $this->branch);
        $e = $this->employee(['name' => 'Rina', 'user_id' => $me->id]);
        $this->structure($e['id'], ['basic' => 20000]);
        $runId = $this->makeRun()->json('data.id');
        $item = PayrollItem::where('payroll_run_id', $runId)->firstOrFail();

        $this->actingAs($me)->get("/api/v1/payroll/payslips/{$item->id}/pdf")->assertNotFound(); // draft
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payroll/runs/{$runId}/approve")->assertOk();

        $this->actingAs($me)->getJson('/api/v1/me/payslips')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.net_pay', 20000);
        $pdf = $this->actingAs($me)->get("/api/v1/payroll/payslips/{$item->id}/pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($other)->get("/api/v1/payroll/payslips/{$item->id}/pdf")->assertForbidden();
        $this->actingAs($me)->getJson('/api/v1/hr/employees')->assertForbidden();

        $sheet = $this->actingAs($this->accountant)->get("/api/v1/payroll/runs/{$runId}/sheet");
        $sheet->assertOk();
        $this->assertStringStartsWith('%PDF', $sheet->getContent());
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
