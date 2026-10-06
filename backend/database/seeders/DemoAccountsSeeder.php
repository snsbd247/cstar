<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AccountMap;
use App\Services\CashClosingService;
use App\Services\ExpenseService;
use App\Services\VoucherService;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 11 — ALL AMOUNTS ARE SAMPLES:
 *  - opening balances on the 1st (owner's capital in the bank, a cash float)
 *  - everyday expenses (one large one waiting for approval), monthly rent as a recurring expense
 *  - cash deposited to the bank (contra) and the receptionist's cash closing for today
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(VoucherService $vouchers, ExpenseService $expenses, AccountMap $map, CashClosingService $closings): void
    {
        if (Voucher::exists()) {
            return;
        }
        $accountant = User::where('email', 'accounts@cstar.test')->firstOrFail();
        $admin = User::where('email', 'branchadmin@cstar.test')->firstOrFail();
        $reception = User::where('email', 'reception@cstar.test')->firstOrFail();
        $branch = Branch::where('code', 'HQ')->first() ?? Branch::firstOrFail();
        auth()->setUser($accountant);

        $cash = $map->cashFor($branch->id);
        $bank = Account::where('code', '1131')->firstOrFail();
        $code = fn (string $c) => Account::where('code', $c)->value('id');
        $category = fn (string $name) => ExpenseCategory::where('name', $name)->value('id');
        $first = today()->startOfMonth();

        // Opening balances (go-live, decision A9).
        $opening = $vouchers->create([
            'type' => 'journal', 'date' => $first->toDateString(), 'branch_id' => $branch->id, 'narration' => 'Opening balances at go-live (demo)',
            'lines' => [
                ['account_id' => $bank->id, 'debit' => 500000, 'memo' => 'Bank statement balance'],
                ['account_id' => $cash->id, 'debit' => 10000, 'memo' => 'Cash float'],
                ['account_id' => $code('1620'), 'debit' => 150000, 'memo' => 'Therapy equipment at cost'],
                ['account_id' => $code('3100'), 'credit' => 660000],
            ],
        ], $accountant);
        $vouchers->approve($vouchers->submit($opening, $accountant), $admin);

        // Everyday expenses.
        foreach ([
            [$first->copy()->addDays(1), 'Internet & mobile', 2500, $bank, 'Link3', null],
            [$first->copy()->addDays(2), 'Tea, snacks & guests', 650, $cash, 'Local shop', $reception],
            [$first->copy()->addDays(3), 'Therapy & training materials', 4200, $cash, 'Toy & sensory kit', null],
            [today(), 'Electricity bill', 3800, $bank, 'DESCO', null],
        ] as [$date, $cat, $amount, $from, $payee, $by]) {
            if ($date->lte(today())) {
                $expenses->create(['date' => $date->toDateString(), 'branch_id' => $branch->id, 'expense_category_id' => $category($cat),
                    'amount' => $amount, 'paid_from_account_id' => $from->id, 'payee' => $payee], $by ?? $accountant);
            }
        }
        // Above the ৳5,000 limit → waits for the branch admin.
        $expenses->create(['date' => today()->toDateString(), 'branch_id' => $branch->id, 'expense_category_id' => $category('Repair & maintenance'),
            'amount' => 18500, 'paid_from_account_id' => $bank->id, 'payee' => 'AC servicing', 'description' => 'Two split ACs, gas refill'], $accountant);

        RecurringExpense::create(['branch_id' => $branch->id, 'expense_category_id' => $category('Rent'), 'amount' => 45000, 'day_of_month' => 5,
            'paid_from_account_id' => $bank->id, 'payee' => 'Landlord (demo)', 'description' => 'Office rent', 'created_by' => $accountant->id]);
        $expenses->generateRecurring($accountant);

        // Cash deposited to the bank (contra voucher).
        $deposit = $vouchers->create([
            'type' => 'contra', 'date' => today()->toDateString(), 'branch_id' => $branch->id, 'narration' => 'Cash deposited to bank (demo)',
            'lines' => [['account_id' => $bank->id, 'debit' => 5000], ['account_id' => $cash->id, 'credit' => 5000]],
        ], $accountant);
        $vouchers->submit($deposit, $accountant);

        // Today's cash closing by the receptionist, counted exactly, received by the branch admin.
        $expected = $closings->expected($reception, $branch->id, today());
        if ($expected['expected_cash'] > 0) {
            $closing = $closings->close($reception, ['branch_id' => $branch->id, 'counted_cash' => $expected['expected_cash']]);
            $closings->receive($closing, $admin);
        }
    }
}
