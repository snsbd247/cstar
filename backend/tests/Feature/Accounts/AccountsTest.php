<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalLine;
use App\Models\Patient;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\AccountMap;
use App\Services\ExpenseService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    private Branch $branch;

    private User $reception;

    private User $accountant;

    private User $branchAdmin;

    private Account $cash;

    private ExpenseCategory $electricity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->seed([ChartOfAccountsSeeder::class, ExpenseCategorySeeder::class]);
        $this->reception = $this->userWithRole(Role::Receptionist, $this->branch);
        $this->accountant = $this->userWithRole(Role::Accountant, $this->branch);
        $this->branchAdmin = $this->userWithRole(Role::BranchAdmin, $this->branch);
        $this->cash = app(AccountMap::class)->cashFor($this->branch->id);
        $this->electricity = ExpenseCategory::where('name', 'Electricity bill')->firstOrFail();
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    private function balance(Account $a): float
    {
        $dr = (float) JournalLine::where('account_id', $a->id)->sum('debit');
        $cr = (float) JournalLine::where('account_id', $a->id)->sum('credit');

        return round($a->normal_balance === 'debit' ? $dr - $cr : $cr - $dr, 2);
    }

    private function expense(float $amount, ?User $as = null, ?Account $from = null)
    {
        return $this->actingAs($as ?? $this->accountant)->postJson('/api/v1/accounts/expenses', [
            'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'expense_category_id' => $this->electricity->id,
            'amount' => $amount, 'paid_from_account_id' => ($from ?? $this->cash)->id, 'payee' => 'DESCO',
        ]);
    }

    private function assertBooksBalance(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_small_expense_posts_at_once_as_a_payment_voucher(): void
    {
        $this->expense(3000)->assertCreated()->assertJsonPath('data.status', 'posted');

        $this->assertSame(3000.0, $this->balance($this->account('5410')));
        $this->assertSame(-3000.0, $this->balance($this->cash));
        $this->assertMatchesRegularExpression('/^PV-\d{4}-\d{5}$/', Expense::firstOrFail()->voucher->voucher_no);
        $this->assertBooksBalance();
    }

    public function test_large_expense_waits_for_a_second_person(): void
    {
        $this->expense(12000)->assertCreated()->assertJsonPath('data.status', 'submitted');
        $voucher = Expense::firstOrFail()->voucher;
        $this->assertSame(0.0, $this->balance($this->account('5410')));

        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/vouchers/{$voucher->id}/approve")->assertJsonValidationErrors('voucher');
        $this->actingAs($this->reception)->postJson("/api/v1/accounts/vouchers/{$voucher->id}/approve")->assertForbidden();
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/accounts/vouchers/{$voucher->id}/approve")->assertOk()->assertJsonPath('data.status', 'posted');

        $this->assertSame('posted', Expense::firstOrFail()->status);
        $this->assertSame(12000.0, $this->balance($this->account('5410')));
    }

    public function test_rejected_expense_is_never_posted(): void
    {
        $this->expense(9000);
        $voucher = Expense::firstOrFail()->voucher;
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/accounts/vouchers/{$voucher->id}/reject", ['reason' => 'No bill attached'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame('rejected', Expense::firstOrFail()->status);
        $this->assertSame(0, JournalLine::count());
    }

    public function test_front_desk_pays_expenses_only_from_cash(): void
    {
        $this->expense(500, $this->reception, $this->account('1131'))->assertJsonValidationErrors('paid_from_account_id');
        $this->expense(500, $this->reception)->assertCreated();
        $this->actingAs($this->reception)->getJson('/api/v1/accounts/vouchers')->assertForbidden();
    }

    public function test_vouchers_must_balance_and_match_their_type(): void
    {
        $post = fn (string $type, array $lines) => $this->actingAs($this->accountant)->postJson('/api/v1/accounts/vouchers', [
            'type' => $type, 'date' => today()->toDateString(), 'branch_id' => $this->branch->id, 'narration' => 'Test', 'lines' => $lines, 'submit' => true,
        ]);
        $bank = $this->account('1131');

        $post('journal', [['account_id' => $this->account('5600')->id, 'debit' => 100], ['account_id' => $this->cash->id, 'credit' => 90]])->assertJsonValidationErrors('lines');
        $post('contra', [['account_id' => $bank->id, 'debit' => 100], ['account_id' => $this->account('5600')->id, 'credit' => 100]])->assertJsonValidationErrors('type');
        $post('receipt', [['account_id' => $this->account('5600')->id, 'debit' => 100], ['account_id' => $this->account('4900')->id, 'credit' => 100]])->assertJsonValidationErrors('type');
        $post('journal', [['account_id' => $this->account('5400')->id, 'debit' => 100], ['account_id' => $this->cash->id, 'credit' => 100]])->assertJsonValidationErrors('lines.0.account_id');

        // Cash deposited to the bank (contra), then reversed.
        $id = $post('contra', [['account_id' => $bank->id, 'debit' => 2000], ['account_id' => $this->cash->id, 'credit' => 2000]])
            ->assertCreated()->assertJsonPath('data.status', 'posted')->json('data.id');
        $this->assertSame(2000.0, $this->balance($bank));

        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/vouchers/{$id}/reverse", ['reason' => 'Deposit slip bounced'])
            ->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->assertSame(0.0, $this->balance($bank));
        $this->actingAs($this->accountant)->deleteJson("/api/v1/accounts/vouchers/{$id}")->assertJsonValidationErrors('voucher');
        $this->assertBooksBalance();
    }

    public function test_closed_months_take_no_vouchers_and_close_in_order(): void
    {
        $this->actingAs($this->accountant)->getJson('/api/v1/accounts/periods')->assertOk()->assertJsonCount(12, 'data');
        $last = AccountingPeriod::where('year', today()->subMonth()->year)->where('month', today()->subMonth()->month)->firstOrFail();
        $current = AccountingPeriod::where('year', today()->year)->where('month', today()->month)->firstOrFail();

        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/periods/{$current->id}/close")->assertJsonValidationErrors('period');
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/accounts/periods/{$last->id}/close")->assertForbidden();
        AccountingPeriod::where('end_date', '<', $last->start_date)->update(['status' => 'closed']);
        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/periods/{$last->id}/close")->assertOk();

        $this->actingAs($this->accountant)->postJson('/api/v1/accounts/expenses', [
            'date' => $last->end_date->toDateString(), 'branch_id' => $this->branch->id, 'expense_category_id' => $this->electricity->id,
            'amount' => 100, 'paid_from_account_id' => $this->cash->id,
        ])->assertJsonValidationErrors('date');

        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/periods/{$last->id}/reopen", ['reason' => 'Missed bill'])->assertOk();
    }

    public function test_cash_closing_books_a_shortage_and_locks_the_day(): void
    {
        $patient = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $paymentId = $this->actingAs($this->reception)->postJson("/api/v1/patients/{$patient->id}/payments", [
            'amount' => 5000, 'method' => 'cash', 'branch_id' => $this->branch->id,
        ])->assertCreated()->json('data.id');
        $this->expense(300, $this->reception);

        $this->actingAs($this->reception)->getJson("/api/v1/accounts/cash-closings/expected?branch_id={$this->branch->id}")
            ->assertOk()->assertJsonPath('data.expected_cash', 4700)->assertJsonPath('data.by_method.cash', 5000);

        $close = fn (array $extra) => $this->actingAs($this->reception)->postJson('/api/v1/accounts/cash-closings', ['branch_id' => $this->branch->id, ...$extra]);
        $close(['denominations' => ['1000' => 4, '500' => 1, '100' => 1]])->assertJsonValidationErrors('reason');
        $id = $close(['denominations' => ['1000' => 4, '500' => 1, '100' => 1], 'reason' => 'Gave change from own pocket?'])
            ->assertCreated()->assertJsonPath('data.counted_cash', 4600)->assertJsonPath('data.difference', -100)->json('data.id');

        $this->actingAs($this->reception)->postJson("/api/v1/accounts/cash-closings/{$id}/receive")->assertForbidden();
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/accounts/cash-closings/{$id}/receive")->assertOk()->assertJsonPath('data.status', 'received');
        $this->assertSame(100.0, $this->balance($this->account('5970')));
        $this->assertSame(4600.0, $this->balance($this->cash));

        // The day is locked for the front desk and the branch admin; the accountant can still fix it.
        $this->actingAs($this->branchAdmin)->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Typo in amount'])->assertJsonValidationErrors('payment');
        $this->actingAs($this->accountant)->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Typo in amount'])->assertOk();
        $this->assertBooksBalance();
    }

    public function test_reports_agree_with_each_other(): void
    {
        $patient = Patient::factory()->create(['home_branch_id' => $this->branch->id]);
        $this->actingAs($this->accountant)->postJson('/api/v1/invoices', [
            'patient_id' => $patient->id, 'branch_id' => $this->branch->id, 'issue' => true,
            'items' => [['item_type' => 'assessment', 'unit_price' => 5000]],
        ])->assertCreated();
        $this->actingAs($this->accountant)->postJson("/api/v1/patients/{$patient->id}/payments", ['amount' => 3000, 'method' => 'cash', 'branch_id' => $this->branch->id]);
        $this->expense(1200);

        $tb = $this->actingAs($this->accountant)->getJson('/api/v1/accounts/reports/trial-balance')->assertOk()->json('data');
        $this->assertEquals($tb['total_debit'], $tb['total_credit']);

        $this->actingAs($this->accountant)->getJson('/api/v1/accounts/reports/income-statement')
            ->assertOk()->assertJsonPath('data.income.total', 5000)->assertJsonPath('data.expense.total', 1200)->assertJsonPath('data.net_profit', 3800);

        $bs = $this->actingAs($this->accountant)->getJson('/api/v1/accounts/reports/balance-sheet')->assertOk()->json('data');
        $this->assertTrue($bs['balanced']);
        $this->assertEquals(3800, $bs['assets']['total']); // cash 3,000 − 1,200 + receivable 2,000
        $this->assertEquals(3800, $bs['equity']['profit_to_date']);

        $ledger = $this->actingAs($this->accountant)->getJson("/api/v1/accounts/reports/ledger?account_id={$this->cash->id}")->assertOk()->json('data');
        $this->assertEquals(1800, $ledger['closing']);
        $this->assertCount(2, $ledger['rows']);

        $this->actingAs($this->accountant)->getJson('/api/v1/accounts/dashboard')->assertOk()->assertJsonPath('data.month.profit', 3800);

        foreach (['trial-balance', 'income-statement', 'balance-sheet', 'day-book', "ledger?account_id={$this->cash->id}&"] as $report) {
            $url = '/api/v1/accounts/reports/'.$report.(str_contains($report, '?') ? 'format=pdf' : '?format=pdf');
            $pdf = $this->actingAs($this->accountant)->get($url);
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), $report);
        }
        $this->actingAs($this->reception)->getJson('/api/v1/accounts/reports/trial-balance')->assertForbidden();
    }

    public function test_recurring_expenses_are_raised_once_a_month_as_drafts(): void
    {
        RecurringExpense::create([
            'branch_id' => $this->branch->id, 'expense_category_id' => ExpenseCategory::where('name', 'Rent')->value('id'),
            'amount' => 50000, 'day_of_month' => 1, 'paid_from_account_id' => $this->account('1131')->id, 'payee' => 'Landlord',
        ]);

        $service = app(ExpenseService::class);
        $this->assertSame(1, $service->generateRecurring($this->accountant));
        $this->assertSame(0, $service->generateRecurring($this->accountant));
        $expense = Expense::firstOrFail();
        $this->assertSame('draft', $expense->status);
        $this->assertSame(0, JournalLine::count());

        $this->actingAs($this->accountant)->postJson("/api/v1/accounts/expenses/{$expense->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    }
}
