<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\AccountMap;
use App\Services\LedgerService;
use App\Services\PeriodService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountsCTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private User $branchAdmin;

    private Account $cash;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
        $this->branchAdmin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->cash = app(AccountMap::class)->cashFor($this->branch->id);
        $this->bank = $this->account('1131');
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    private function balance(Account $a): float
    {
        $net = (float) JournalLine::where('account_id', $a->id)->sum('debit') - (float) JournalLine::where('account_id', $a->id)->sum('credit');

        return round($a->normal_balance === 'debit' ? $net : -$net, 2);
    }

    private function as(?User $u = null)
    {
        return $this->actingAs($u ?? $this->accountant);
    }

    public function test_vendor_bills_and_payments_move_payables(): void
    {
        $vendor = $this->as()->postJson('/api/v1/accounts/vendors', ['name' => 'Toy World', 'type' => 'supplier', 'opening_balance' => 1000])->assertCreated()->json('data');
        $this->assertEquals(1000, $this->balance($this->account('2100')));

        $this->as()->postJson("/api/v1/accounts/vendors/{$vendor['id']}/bills", [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'vendor_ref' => 'TW-55',
            'items' => [['account_id' => $this->account('5500')->id, 'description' => 'Sensory kit', 'amount' => 4000], ['account_id' => $this->cash->id, 'description' => 'x', 'amount' => 1]],
        ])->assertJsonValidationErrors('items.1.account_id');
        $bill = $this->as()->postJson("/api/v1/accounts/vendors/{$vendor['id']}/bills", [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id,
            'items' => [['account_id' => $this->account('5500')->id, 'description' => 'Sensory kit', 'amount' => 4000]],
        ])->assertCreated()->json('data');
        $this->assertEquals(4000, $this->balance($this->account('5500')));
        $this->assertEquals(5000, $this->balance($this->account('2100')));

        $this->as()->postJson("/api/v1/accounts/vendors/{$vendor['id']}/payments", [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'amount' => 6000, 'paid_from_account_id' => $this->bank->id,
        ])->assertJsonValidationErrors('amount');
        $this->as()->postJson("/api/v1/accounts/vendors/{$vendor['id']}/payments", [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'amount' => 2500, 'paid_from_account_id' => $this->bank->id,
        ])->assertCreated();

        $show = $this->as()->getJson("/api/v1/accounts/vendors/{$vendor['id']}")->assertOk();
        $show->assertJsonPath('data.balance', 2500)->assertJsonPath('data.bills.0.status', 'partially_paid');
        $this->assertEquals(2500, $this->balance($this->account('2100')));
        $this->as()->postJson("/api/v1/accounts/vendor-bills/{$bill['id']}/void", ['reason' => 'Duplicate bill'])->assertJsonValidationErrors('bill');
    }

    public function test_bank_reconciliation_matches_books_and_books_bank_charges(): void
    {
        $ledger = app(LedgerService::class);
        $ledger->post('test.deposit', today()->subDays(3), $this->branch->id, 'Cash deposited', [
            ['account' => $this->bank, 'debit' => 50000], ['account' => $this->cash, 'credit' => 50000]]);
        $ledger->post('test.cheque', today()->subDays(2), $this->branch->id, 'Rent cheque', [
            ['account' => $this->account('5300'), 'debit' => 20000], ['account' => $this->bank, 'credit' => 20000]]);
        $ledger->post('test.cheque2', today()->subDay(), $this->branch->id, 'Cheque not yet cleared', [
            ['account' => $this->account('5600'), 'debit' => 3000], ['account' => $this->bank, 'credit' => 3000]]);

        // Bank statement: deposit, rent cheque, and a ৳115 charge the books don't have yet. Balance 29,885.
        $rec = $this->as()->postJson('/api/v1/accounts/reconciliations', ['account_id' => $this->bank->id, 'statement_date' => today()->toDateString(), 'statement_balance' => 29885])->assertCreated()->json('data');
        $csv = "Date,Description,Debit,Credit\n".today()->subDays(3)->toDateString().",CASH DEPOSIT,,50000\n".today()->subDays(2)->toDateString().",CHQ 0012,20000,\n".today()->toDateString().",SMS + maintenance charge,115,\n";
        $this->as()->post("/api/v1/accounts/reconciliations/{$rec['id']}/import", ['file' => UploadedFile::fake()->createWithContent('st.csv', $csv)])
            ->assertOk()->assertJsonCount(3, 'data.lines');

        $res = $this->as()->postJson("/api/v1/accounts/reconciliations/{$rec['id']}/auto-match")->assertOk();
        $this->assertSame('2 lines matched.', $res->json('message'));
        $this->as()->postJson("/api/v1/accounts/reconciliations/{$rec['id']}/complete")->assertJsonValidationErrors('reconciliation');

        $charge = collect($res->json('data.lines'))->firstWhere('status', 'unmatched');
        $res = $this->as()->postJson("/api/v1/accounts/statement-lines/{$charge['id']}/adjust")->assertOk();
        $this->assertEquals(115, $this->balance($this->account('5950')));
        $res->assertJsonPath('data.summary.outstanding_total', -3000)->assertJsonPath('data.summary.difference', 0);

        $this->as()->postJson("/api/v1/accounts/reconciliations/{$rec['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(3, JournalLine::whereNotNull('bank_reconciliation_id')->count());
    }

    public function test_assets_depreciate_monthly_and_disposal_books_a_loss(): void
    {
        $asset = $this->as()->postJson('/api/v1/accounts/fixed-assets', [
            'name' => 'Therapy swing', 'account_id' => $this->account('1620')->id, 'branch_id' => $this->branch->id,
            'purchase_date' => today()->subMonths(2)->startOfMonth()->toDateString(), 'cost' => 36000, 'salvage_value' => 0,
            'useful_life_months' => 36, 'paid_from_account_id' => $this->bank->id,
        ])->assertCreated()->json('data');
        $this->assertEquals(36000, $this->balance($this->account('1620')));

        // Three months (purchase month through this month) at ৳1,000.
        $this->as()->postJson('/api/v1/accounts/fixed-assets/depreciate', ['month' => today()->format('Y-m')])->assertOk()->assertJsonPath('data.amount', 3000);
        $this->as()->postJson('/api/v1/accounts/fixed-assets/depreciate', ['month' => today()->format('Y-m')])->assertOk()->assertJsonPath('data.amount', 0);
        $this->assertEquals(3000, $this->balance($this->account('5960')));
        $this->assertEquals(33000, FixedAsset::findOrFail($asset['id'])->bookValue());

        $this->as($this->branchAdmin)->postJson("/api/v1/accounts/fixed-assets/{$asset['id']}/dispose", [
            'date' => today()->toDateString(), 'amount' => 30000, 'received_in_account_id' => $this->cash->id, 'reason' => 'Sold',
        ])->assertOk()->assertJsonPath('data.status', 'disposed');
        $this->assertEquals(0, $this->balance($this->account('1620')));
        $this->assertEquals(0, $this->balance($this->account('1690')));
        $this->assertEquals(3000, $this->balance($this->account('5990'))); // loss
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_budget_vs_actual_and_cash_flow(): void
    {
        app(LedgerService::class)->post('test.rent', today(), $this->branch->id, 'Rent', [
            ['account' => $this->account('5300'), 'debit' => 45000], ['account' => $this->bank, 'credit' => 45000]]);
        app(LedgerService::class)->post('test.capital', today(), $this->branch->id, 'Owner capital', [
            ['account' => $this->bank, 'debit' => 100000], ['account' => $this->account('3100'), 'credit' => 100000]]);

        $years = $this->as()->getJson('/api/v1/accounts/budgets')->assertOk()->json('years');
        $budget = $this->as()->postJson('/api/v1/accounts/budgets', [
            'name' => 'Head office '.$years[0]['name'], 'fiscal_year_id' => $years[0]['id'], 'branch_id' => null,
            'lines' => [['account_id' => $this->account('5300')->id, 'annual_amount' => 480000]],
        ])->assertCreated()->json('data');
        $this->as()->getJson("/api/v1/accounts/budgets/{$budget['id']}/vs-actual?from=".today()->startOfMonth()->toDateString().'&to='.today()->toDateString())
            ->assertOk()->assertJsonPath('data.expense.0.budget', 40000)->assertJsonPath('data.expense.0.actual', 45000)->assertJsonPath('data.expense.0.variance', 5000);
        $this->as($this->userWithRole(Role::Receptionist, $this->branch))->postJson('/api/v1/accounts/budgets', [])->assertForbidden();

        $cf = $this->as()->getJson('/api/v1/accounts/reports/cash-flow')->assertOk()->json('data');
        $this->assertEquals(-45000, $cf['sections']['operating']['total']);
        $this->assertEquals(100000, $cf['sections']['financing']['total']);
        $this->assertEquals(55000, $cf['closing'] - $cf['opening']);
        $pdf = $this->as()->get('/api/v1/accounts/reports/cash-flow?format=pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_year_end_moves_profit_to_retained_earnings(): void
    {
        $ledger = app(LedgerService::class);
        $lastYearDay = Carbon::create(today()->month >= 7 ? today()->year - 1 : today()->year - 2, 9, 15);
        $ledger->post('test.income', $lastYearDay, $this->branch->id, 'Fees', [['account' => $this->bank, 'debit' => 80000], ['account' => $this->account('4300'), 'credit' => 80000]]);
        $ledger->post('test.expense', $lastYearDay, $this->branch->id, 'Rent', [['account' => $this->account('5300'), 'debit' => 30000], ['account' => $this->bank, 'credit' => 30000]]);
        app(PeriodService::class)->year($lastYearDay);
        $year = FiscalYear::whereDate('start_date', '<=', $lastYearDay)->whereDate('end_date', '>=', $lastYearDay)->firstOrFail();

        $this->as()->postJson("/api/v1/accounts/fiscal-years/{$year->id}/close")->assertJsonValidationErrors('year');
        $this->as($this->branchAdmin)->postJson("/api/v1/accounts/fiscal-years/{$year->id}/close")->assertForbidden();
        AccountingPeriod::where('fiscal_year_id', $year->id)->update(['status' => 'closed']);
        $this->as()->postJson("/api/v1/accounts/fiscal-years/{$year->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertEquals(50000, $this->balance($this->account('3300')));
        $this->assertEquals(0, $this->balance($this->account('4300')));
        $this->assertSame($year->end_date->toDateString(), JournalEntry::where('event', 'year.closed')->firstOrFail()->date->toDateString());

        // The closed year's P&L still shows what happened; the balance sheet counts the profit once.
        $this->as()->getJson("/api/v1/accounts/reports/income-statement?from={$year->start_date->toDateString()}&to={$year->end_date->toDateString()}")
            ->assertOk()->assertJsonPath('data.net_profit', 50000);
        $bs = $this->as()->getJson('/api/v1/accounts/reports/balance-sheet')->assertOk()->json('data');
        $this->assertTrue($bs['balanced']);
        $this->assertEquals(0, $bs['equity']['profit_to_date']);
        $this->assertEquals(50000, $bs['equity']['total']);
        $this->as()->postJson("/api/v1/accounts/fiscal-years/{$year->id}/close")->assertJsonValidationErrors('year');
    }
}
