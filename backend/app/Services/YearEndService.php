<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Year-end closing (Accounts §১১): once all twelve months are closed, every income and expense balance
 * of the year moves to Retained Earnings in one closing entry dated the last day of the year.
 */
class YearEndService
{
    public function __construct(private LedgerService $ledger, private AccountMap $accounts) {}

    public function close(FiscalYear $year, User $user): FiscalYear
    {
        if ($year->status === 'closed') {
            throw ValidationException::withMessages(['year' => 'This year is already closed.']);
        }
        if ($year->periods()->count() < 12 || $year->periods()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['year' => 'Close all twelve months of the year first.']);
        }

        return DB::transaction(function () use ($year, $user) {
            $sums = JournalLine::query()
                ->whereHas('account', fn ($a) => $a->whereIn('type', ['income', 'expense']))
                ->whereHas('entry', fn ($e) => $e->whereBetween('date', [$year->start_date->toDateString(), $year->end_date->toDateString()]))
                ->selectRaw('account_id, branch_id, SUM(debit) as dr, SUM(credit) as cr')->groupBy('account_id', 'branch_id')->get();
            $accounts = Account::whereIn('id', $sums->pluck('account_id'))->get()->keyBy('id');

            $lines = [];
            $byBranch = []; // retained earnings is credited per branch so branch balance sheets still balance
            foreach ($sums as $s) {
                $net = round((float) $s->dr - (float) $s->cr, 2); // debit balance (expense) > 0
                if (abs($net) < 0.005) {
                    continue;
                }
                $lines[] = ['account' => $accounts[$s->account_id], 'branch_id' => $s->branch_id, $net > 0 ? 'credit' : 'debit' => abs($net)];
                $byBranch[$s->branch_id ?? 0] = ($byBranch[$s->branch_id ?? 0] ?? 0) - $net;
            }
            foreach ($byBranch as $branchId => $result) {
                $result = round($result, 2);
                if ($result != 0) {
                    $lines[] = ['account' => $this->accounts->system('retained_earnings'), 'branch_id' => $branchId ?: null,
                        $result > 0 ? 'credit' : 'debit' => abs($result), 'memo' => "Net result {$year->name}"];
                }
            }
            $profit = round(array_sum($byBranch), 2);

            $entry = $lines ? $this->ledger->post('year.closed', $year->end_date, null, "Year-end closing {$year->name}", $lines, $year, 'journal', keepDate: true) : null;
            $year->update(['status' => 'closed', 'closing_entry_id' => $entry?->id, 'closed_by' => $user->id, 'closed_at' => now()]);
            AuditLogger::log('year.closed', $year, new: ['profit' => $profit]);

            return $year;
        });
    }
}
