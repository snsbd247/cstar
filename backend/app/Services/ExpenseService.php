<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simple expense form (Accounts §৫): "Electricity ৳5,000 from Cash" — the system writes the payment voucher
 * (Dr expense account / Cr cash-bank-MFS). Front-desk staff may only pay from their branch cash or petty cash.
 */
class ExpenseService
{
    public function __construct(private IdGenerator $ids, private VoucherService $vouchers) {}

    public function create(array $data, User $user, bool $submit = true, ?RecurringExpense $recurring = null): Expense
    {
        $category = ExpenseCategory::with('account')->findOrFail($data['expense_category_id']);
        $paidFrom = Account::findOrFail($data['paid_from_account_id']);
        $this->assertPaidFromAllowed($paidFrom, (int) $data['branch_id'], $user);

        return DB::transaction(function () use ($data, $user, $submit, $recurring, $category, $paidFrom) {
            $date = Carbon::parse($data['date']);
            $narration = trim($category->name.($data['payee'] ?? null ? " — {$data['payee']}" : '').($data['description'] ?? null ? ": {$data['description']}" : ''));

            $voucher = $this->vouchers->create([
                'type' => 'payment', 'date' => $date->toDateString(), 'branch_id' => $data['branch_id'], 'narration' => $narration,
                'attachment_path' => $data['attachment_path'] ?? null,
                'lines' => [
                    ['account_id' => $category->account_id, 'debit' => $data['amount'], 'memo' => $data['reference'] ?? null],
                    ['account_id' => $paidFrom->id, 'credit' => $data['amount']],
                ],
            ], $user);

            $expense = Expense::create([
                'expense_no' => $this->ids->next('expense', 'EXP', 5, $date->year),
                'date' => $date,
                'branch_id' => $data['branch_id'],
                'expense_category_id' => $category->id,
                'amount' => $data['amount'],
                'paid_from_account_id' => $paidFrom->id,
                'payee' => $data['payee'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'voucher_id' => $voucher->id,
                'recurring_expense_id' => $recurring?->id,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            if ($submit) {
                $this->vouchers->submit($voucher, $user);
            }

            return $expense->refresh();
        });
    }

    /** Daily: raise this month's recurring expenses as drafts on their day (Accounts §৫). */
    public function generateRecurring(User $system, ?Carbon $today = null): int
    {
        $today ??= today();
        $period = $today->format('Y-m');
        $created = 0;

        RecurringExpense::where('is_active', true)
            ->where('day_of_month', '<=', $today->day)
            ->where(fn ($q) => $q->whereNull('last_generated_period')->orWhere('last_generated_period', '<', $period))
            ->get()
            ->each(function (RecurringExpense $r) use ($system, $today, $period, &$created) {
                $day = min($r->day_of_month, $today->daysInMonth);
                $this->create([
                    'date' => $today->copy()->day($day)->toDateString(), 'branch_id' => $r->branch_id, 'expense_category_id' => $r->expense_category_id,
                    'amount' => $r->amount, 'paid_from_account_id' => $r->paid_from_account_id, 'payee' => $r->payee,
                    'description' => trim(($r->description ?? '').' (monthly — '.$today->format('M Y').')'),
                ], $system, submit: false, recurring: $r);
                $r->update(['last_generated_period' => $period]);
                $created++;
            });

        return $created;
    }

    /** Reception and other non-accounts staff pay small expenses only from their branch cash box or petty cash. */
    private function assertPaidFromAllowed(Account $account, int $branchId, User $user): void
    {
        if (! in_array($account->subtype, ['cash', 'bank', 'mfs'], true) || $account->is_group || ! $account->is_active) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Pay from a cash, bank or bKash/Nagad account.']);
        }
        if ($account->branch_id && $account->branch_id !== $branchId) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'That cash box belongs to another branch.']);
        }
        if (! $user->can(Permission::ACCOUNTS_VOUCHER_CREATE) && $account->subtype !== 'cash') {
            throw ValidationException::withMessages(['paid_from_account_id' => 'You can only pay expenses from cash in hand or petty cash.']);
        }
    }
}
