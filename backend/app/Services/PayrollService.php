<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\InvoiceItem;
use App\Models\PackageUsage;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll (Accounts §৭.৩):
 *   create run → calculate (fixed + session pay + bonus − absence − advance instalment − tax) → adjust
 *   → approve by a second person = post (Dr salary expense by department / Cr Salary Payable, Staff Advances, Tax)
 *   → pay (Dr Salary Payable / Cr cash or bank), all at once or person by person.
 * Session pay is counted straight from finalized therapy sessions, so therapist and accountant always agree.
 */
class PayrollService
{
    private const EXPENSE_BY_DEPARTMENT = ['therapist' => '5110', 'trainer' => '5120', 'admin' => '5130', 'support' => '5140'];

    public function __construct(private LedgerService $ledger, private AccountMap $accounts) {}

    public function create(array $data, User $user): PayrollRun
    {
        $month = Carbon::createFromFormat('Y-m-d', $data['month'].'-01');
        if ($month->copy()->startOfMonth()->isAfter(today())) {
            throw ValidationException::withMessages(['month' => 'Payroll cannot be made for a future month.']);
        }
        $type = $data['type'] ?? 'salary';
        $title = $type === 'bonus' ? ($data['title'] ?? 'Festival bonus') : null;
        $exists = PayrollRun::where('month', $data['month'])->where('branch_id', $data['branch_id'])->where('type', $type)->where('title', $title)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['month' => 'This payroll already exists for the month.']);
        }

        return DB::transaction(function () use ($data, $user, $type, $title, $month) {
            $branchCode = Branch::findOrFail($data['branch_id'])->code;
            $run = PayrollRun::create([
                'run_no' => sprintf('%s-%s-%s%s', $type === 'bonus' ? 'BN' : 'PR', $month->format('Y-m'), $branchCode,
                    $type === 'bonus' ? '-'.(PayrollRun::where('type', 'bonus')->where('month', $data['month'])->count() + 1) : ''),
                'month' => $data['month'], 'branch_id' => $data['branch_id'], 'type' => $type, 'title' => $title,
                'status' => 'draft', 'prepared_by' => $user->id, 'notes' => $data['notes'] ?? null,
            ]);
            $this->calculate($run);
            app(NotificationService::class)->toStaff(Permission::ACCOUNTS_PAYROLL_APPROVE, $run->branch_id, 'payroll.prepared',
                "{$run->label()} is ready for approval", '৳'.number_format((float) $run->total_net)." net for {$run->items()->count()} staff (prepared by {$user->name})",
                "/app/payroll/{$run->id}", $user->id);

            return $run;
        });
    }

    /** (Re)builds every line from salary structures, sessions and advances. Manual adjustments are lost. */
    public function calculate(PayrollRun $run): PayrollRun
    {
        $this->assertDraft($run);
        $start = $run->start();
        $end = $run->end();

        return DB::transaction(function () use ($run, $start, $end) {
            $run->items()->delete();
            $employees = Employee::with(['therapist'])->where('branch_id', $run->branch_id)
                ->whereDate('joining_date', '<=', $end)
                ->where(fn ($q) => $q->whereNull('left_date')->orWhereDate('left_date', '>=', $start))
                ->where('status', '!=', 'inactive')
                ->orderBy('department')->orderBy('name')->get();

            foreach ($employees as $employee) {
                $line = $run->type === 'bonus' ? $this->bonusLine($employee, $end) : $this->salaryLine($employee, $start, $end);
                if ($line === null) {
                    continue;
                }
                $item = new PayrollItem(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'department' => $employee->department, ...$line]);
                $item->recalculate();
                $this->capAdvance($item);
                $item->save();
            }

            return $this->refreshTotals($run);
        });
    }

    public function updateItem(PayrollItem $item, array $data): PayrollItem
    {
        $this->assertDraft($item->run);
        $item->fill(Arr::only($data, PayrollItem::ADJUSTABLE));
        if (array_key_exists('advance_deduction', $data)) {
            $item->breakdown = [...($item->breakdown ?? []), 'advances' => $this->spreadAdvance($item->employee_id, (float) $data['advance_deduction'])];
            $item->advance_deduction = round(array_sum(array_column($item->breakdown['advances'], 'amount')), 2);
        }
        $item->recalculate();
        if ($item->net_pay < 0) {
            throw ValidationException::withMessages(['net_pay' => 'Deductions are more than the pay.']);
        }
        $item->save();
        $this->refreshTotals($item->run);

        return $item;
    }

    /** Approval by someone other than the preparer posts the payroll to the books. */
    public function approve(PayrollRun $run, User $user): PayrollRun
    {
        $this->assertDraft($run);
        if (! $user->can(Permission::ACCOUNTS_PAYROLL_APPROVE)) {
            throw ValidationException::withMessages(['payroll' => 'You cannot approve payroll.']);
        }
        if ($run->prepared_by === $user->id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['payroll' => 'Someone other than the preparer must approve the payroll.']);
        }
        $run->load('items.employee');
        if ($run->items->isEmpty()) {
            throw ValidationException::withMessages(['payroll' => 'There is nobody to pay in this payroll.']);
        }

        return DB::transaction(function () use ($run, $user) {
            $debits = [];
            $add = function (string $code, float $amount, ?string $memo = null) use (&$debits) {
                $debits[$code] = ['amount' => round(($debits[$code]['amount'] ?? 0) + $amount, 2), 'memo' => $memo];
            };
            foreach ($run->items as $item) {
                $fixedPart = (float) $item->gross - (float) $item->session_pay;
                $add($run->type === 'bonus' ? '5150' : self::EXPENSE_BY_DEPARTMENT[$item->department], $fixedPart);
                if ((float) $item->session_pay > 0) {
                    $add('5200', (float) $item->session_pay, 'Session pay');
                }
            }

            $lines = [];
            foreach ($debits as $code => $d) {
                $lines[] = $d['amount'] >= 0
                    ? ['account' => $this->byCode($code), 'debit' => $d['amount'], 'memo' => $d['memo']]
                    : ['account' => $this->byCode($code), 'credit' => -$d['amount'], 'memo' => $d['memo']];
            }
            $lines[] = ['account' => $this->accounts->system('salary_payable'), 'credit' => $run->items->sum('net_pay')];
            $lines[] = ['account' => $this->accounts->system('staff_advances'), 'credit' => $run->items->sum('advance_deduction')];
            $lines[] = ['account' => $this->byCode('2500'), 'credit' => $run->items->sum('tax')];
            $lines[] = ['account' => $this->accounts->system('income_other'), 'credit' => $run->items->sum('other_deduction'), 'memo' => 'Other salary deductions'];

            $entry = $this->ledger->post('payroll.posted', $run->end()->isFuture() ? today() : $run->end(), $run->branch_id,
                "{$run->run_no} — {$run->label()} ({$run->items->count()} staff)", $lines, $run);

            // Advance instalments are now recovered.
            foreach ($run->items as $item) {
                foreach ($item->breakdown['advances'] ?? [] as $a) {
                    $advance = EmployeeAdvance::find($a['id']);
                    $balance = max(0, round((float) $advance->balance - $a['amount'], 2));
                    $advance->update(['balance' => $balance, 'status' => $balance > 0 ? 'active' : 'settled']);
                }
            }

            $run->update(['status' => 'posted', 'journal_entry_id' => $entry?->id, 'approved_by' => $user->id, 'approved_at' => now()]);

            return $run;
        });
    }

    /** Undo an approved payroll that has not been paid yet (mistake found) — back to draft. */
    public function reverse(PayrollRun $run, string $reason): PayrollRun
    {
        if ($run->status !== 'posted' || $run->items()->where('paid_amount', '>', 0)->exists()) {
            throw ValidationException::withMessages(['payroll' => 'Only an approved payroll with nobody paid yet can be reopened.']);
        }

        return DB::transaction(function () use ($run, $reason) {
            if ($run->journalEntry) {
                $this->ledger->reverse($run->journalEntry, $reason);
            }
            foreach ($run->items as $item) {
                foreach ($item->breakdown['advances'] ?? [] as $a) {
                    $advance = EmployeeAdvance::find($a['id']);
                    $advance->update(['balance' => round((float) $advance->balance + $a['amount'], 2), 'status' => 'active']);
                }
            }
            $run->update(['status' => 'draft', 'journal_entry_id' => null, 'approved_by' => null, 'approved_at' => null]);
            AuditLogger::log('reopened', $run, new: ['reason' => $reason]);

            return $run;
        });
    }

    /** Pays the selected (or all unpaid) staff from one cash/bank account. */
    public function pay(PayrollRun $run, int $paidFromAccountId, User $user, ?array $itemIds = null): PayrollRun
    {
        if ($run->status === 'draft') {
            throw ValidationException::withMessages(['payroll' => 'Approve the payroll before paying.']);
        }
        $from = Account::findOrFail($paidFromAccountId);
        if (! in_array($from->subtype, ['cash', 'bank', 'mfs'], true) || $from->is_group) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Pay from a cash, bank or bKash/Nagad account.']);
        }

        return DB::transaction(function () use ($run, $from, $itemIds) {
            $items = $run->items()->with('employee')->where('net_pay', '>', 0)->whereNull('paid_at')
                ->when($itemIds, fn ($q) => $q->whereIn('id', $itemIds))->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['payroll' => 'Everyone selected is already paid.']);
            }
            $total = round($items->sum('net_pay'), 2);
            $entry = $this->ledger->post('payroll.paid', today(), $run->branch_id,
                "{$run->run_no} — paid {$items->count()} staff from {$from->name}", [
                    ['account' => $this->accounts->system('salary_payable'), 'debit' => $total],
                    ['account' => $from, 'credit' => $total],
                ], $run, 'payment');
            foreach ($items as $item) {
                $item->update(['paid_amount' => $item->net_pay, 'paid_at' => now(), 'payment_journal_id' => $entry?->id]);
            }
            if (! $run->items()->where('net_pay', '>', 0)->whereNull('paid_at')->exists()) {
                $run->update(['status' => 'paid']);
            }

            return $run;
        });
    }

    public function giveAdvance(Employee $employee, array $data, User $user): EmployeeAdvance
    {
        $from = Account::findOrFail($data['paid_from_account_id']);
        if (! in_array($from->subtype, ['cash', 'bank', 'mfs'], true) || $from->is_group) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Pay from a cash, bank or bKash/Nagad account.']);
        }

        return DB::transaction(function () use ($employee, $data, $user, $from) {
            $advance = EmployeeAdvance::create([
                'employee_id' => $employee->id, 'date' => $data['date'], 'amount' => $data['amount'],
                'installment' => $data['installment'] ?? $data['amount'], 'balance' => $data['amount'],
                'paid_from_account_id' => $from->id, 'reason' => $data['reason'] ?? null, 'status' => 'active', 'created_by' => $user->id,
            ]);
            $entry = $this->ledger->post('advance.given', $data['date'], $employee->branch_id, "Salary advance — {$employee->name}", [
                ['account' => $this->accounts->system('staff_advances'), 'debit' => $data['amount'], 'party' => $employee],
                ['account' => $from, 'credit' => $data['amount']],
            ], $advance, 'payment');
            $advance->update(['journal_entry_id' => $entry?->id]);

            return $advance;
        });
    }

    // ---------------------------------------------------------------------------------------------

    private function salaryLine(Employee $employee, Carbon $start, Carbon $end): ?array
    {
        $structure = $employee->structureOn($end);
        $breakdown = [];
        $fixed = 0.0;

        if ($structure && in_array($employee->pay_type, ['fixed', 'mixed'], true)) {
            // Joined or left during the month → paid for the days worked.
            $from = $employee->joining_date->gt($start) ? $employee->joining_date : $start;
            $to = $employee->left_date && $employee->left_date->lt($end) ? $employee->left_date : $end;
            $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
            $ratio = round($days / $start->daysInMonth, 4);
            foreach ($structure->components() as $name => $amount) {
                $breakdown['allowances'][] = ['name' => $name, 'amount' => round($amount * min(1, $ratio), 2)];
            }
            $fixed = round(array_sum(array_column($breakdown['allowances'] ?? [], 'amount')), 2);
            if ($ratio < 1) {
                $breakdown['prorated_days'] = $days;
            }
        }

        [$sessionCount, $sessionPay, $sessionBreakdown] = $employee->earnsSessions() && $employee->therapist
            ? $this->sessionPay($employee, $structure, $start, $end)
            : [0, 0.0, []];
        if ($sessionBreakdown) {
            $breakdown['sessions'] = $sessionBreakdown;
        }

        $advances = $this->spreadAdvance($employee->id, null);
        if ($advances) {
            $breakdown['advances'] = $advances;
        }

        if ($fixed <= 0 && $sessionPay <= 0 && ! $structure) {
            return null; // no pay set up yet
        }

        return [
            'fixed_amount' => $fixed,
            'session_count' => $sessionCount,
            'session_pay' => $sessionPay,
            'advance_deduction' => round(array_sum(array_column($advances, 'amount')), 2),
            'breakdown' => $breakdown,
        ];
    }

    private function bonusLine(Employee $employee, Carbon $end): ?array
    {
        $structure = $employee->structureOn($end);
        $bonus = $structure && in_array($employee->pay_type, ['fixed', 'mixed'], true) ? (float) $structure->basic : 0;

        return $bonus > 0 ? ['bonus' => $bonus, 'breakdown' => ['note' => 'One basic salary']] : null;
    }

    /** @return array{0: int, 1: float, 2: list<array<string, mixed>>} */
    private function sessionPay(Employee $employee, $structure, Carbon $start, Carbon $end): array
    {
        $sessions = TherapySession::with('service')->where('therapist_id', $employee->therapist->id)->where('status', 'final')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->orderBy('id')->get();
        $count = $sessions->count();

        if ($employee->pay_type === 'revenue_share') {
            $percent = (float) ($structure?->revenue_share_percent ?? 0);
            $revenue = $this->revenueOf($sessions);
            $pay = round($revenue * $percent / 100, 2);

            return [$count, $pay, [['label' => "{$percent}% of session income ৳".number_format($revenue, 2), 'count' => $count, 'amount' => $pay]]];
        }

        // Mixed: the fixed pay already covers the first N sessions.
        $paid = $employee->pay_type === 'mixed' ? $sessions->slice((int) ($structure?->included_sessions ?? 0)) : $sessions;
        $rows = $paid->groupBy('service_id')->map(function (Collection $list) use ($employee, $end) {
            $rate = $this->rateFor($employee, $list->first()->service_id, $end);

            return ['label' => $list->first()->service->name, 'count' => $list->count(), 'rate' => $rate, 'amount' => round($list->count() * $rate, 2)];
        })->values()->all();

        return [$count, round(array_sum(array_column($rows, 'amount')), 2), $rows];
    }

    /** Income earned by these sessions: per-session invoice lines plus package sessions used. */
    private function revenueOf(Collection $sessions): float
    {
        $appointments = $sessions->pluck('appointment_id')->filter();
        $invoiced = (float) InvoiceItem::whereIn('appointment_id', $appointments)->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['draft', 'void']))->sum('line_total');
        $packages = (float) PackageUsage::whereIn('appointment_id', $appointments)->sum('value');

        return round($invoiced + $packages, 2);
    }

    private function rateFor(Employee $employee, int $serviceId, Carbon $on): float
    {
        $rates = $employee->sessionRates()->whereDate('effective_from', '<=', $on)->get();

        return (float) ($rates->firstWhere('service_id', $serviceId)?->rate ?? $rates->firstWhere('service_id', null)?->rate ?? 0);
    }

    /** Advance instalments, oldest first; $total = null uses each advance's own instalment. */
    private function spreadAdvance(int $employeeId, ?float $total): array
    {
        $rows = [];
        foreach (EmployeeAdvance::where('employee_id', $employeeId)->where('status', 'active')->where('balance', '>', 0)->orderBy('date')->get() as $a) {
            $take = $total === null ? min((float) $a->installment, (float) $a->balance) : min($total, (float) $a->balance);
            if ($take <= 0) {
                continue;
            }
            $rows[] = ['id' => $a->id, 'amount' => round($take, 2)];
            if ($total !== null) {
                $total = round($total - $take, 2);
            }
        }

        return $rows;
    }

    /** Never deduct more advance than the pay. */
    private function capAdvance(PayrollItem $item): void
    {
        if ($item->net_pay >= 0) {
            return;
        }
        $allowed = max(0, (float) $item->advance_deduction + (float) $item->net_pay);
        $item->breakdown = [...($item->breakdown ?? []), 'advances' => $this->spreadAdvance($item->employee_id, $allowed)];
        $item->advance_deduction = round(array_sum(array_column($item->breakdown['advances'], 'amount')), 2);
        $item->recalculate();
    }

    private function refreshTotals(PayrollRun $run): PayrollRun
    {
        $items = $run->items()->get();
        $run->update([
            'total_gross' => round($items->sum('gross'), 2),
            'total_deductions' => round($items->sum(fn ($i) => (float) $i->advance_deduction + (float) $i->tax + (float) $i->other_deduction), 2),
            'total_net' => round($items->sum('net_pay'), 2),
        ]);

        return $run;
    }

    private function byCode(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    private function assertDraft(PayrollRun $run): void
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['payroll' => 'This payroll is approved — reopen it to make changes.']);
        }
    }
}
