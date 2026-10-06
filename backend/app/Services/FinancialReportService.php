<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial reports from journal_lines (Accounts §১২). Every figure is a sum of posted lines —
 * nothing is stored twice. Balances are "normal side positive" (assets/expenses on debit, the rest on credit).
 */
class FinancialReportService
{
    /** Lines filtered by date range and branch. */
    private function lines(?Carbon $from, ?Carbon $to, ?int $branchId): Builder
    {
        return JournalLine::query()
            ->whereHas('entry', fn ($q) => $q
                ->when($from, fn ($w) => $w->whereDate('date', '>=', $from))
                ->when($to, fn ($w) => $w->whereDate('date', '<=', $to)))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    /** @return Collection<int, object{account_id: int, dr: string, cr: string}> keyed by account id */
    private function sums(?Carbon $from, ?Carbon $to, ?int $branchId): Collection
    {
        return $this->lines($from, $to, $branchId)
            ->select('account_id', DB::raw('SUM(debit) as dr'), DB::raw('SUM(credit) as cr'))
            ->groupBy('account_id')->get()->keyBy('account_id');
    }

    private static function normal(Account $a, float $dr, float $cr): float
    {
        return round($a->normal_balance === 'debit' ? $dr - $cr : $cr - $dr, 2);
    }

    /** Trial balance up to a date (or for a range): each account on its debit or credit side. */
    public function trialBalance(Carbon $to, ?Carbon $from = null, ?int $branchId = null): array
    {
        $sums = $this->sums($from, $to, $branchId);
        $rows = Account::where('is_group', false)->orderBy('code')->get()
            ->map(function (Account $a) use ($sums) {
                $s = $sums[$a->id] ?? null;
                $net = round(($s ? (float) $s->dr - (float) $s->cr : 0), 2);

                return ['account' => $a->only(['id', 'code', 'name', 'type']), 'debit' => max(0, $net), 'credit' => max(0, -$net)];
            })
            ->filter(fn ($r) => $r['debit'] || $r['credit'])->values();

        return [
            'rows' => $rows,
            'total_debit' => round($rows->sum('debit'), 2),
            'total_credit' => round($rows->sum('credit'), 2),
        ];
    }

    /**
     * General ledger / cash book / bank book for one account: opening balance, every line with a running balance.
     */
    public function ledger(Account $account, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $before = $this->lines(null, $from->copy()->subDay(), $branchId)->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) as dr, COALESCE(SUM(credit),0) as cr')->first();
        $opening = self::normal($account, (float) $before->dr, (float) $before->cr);

        $lines = $this->lines($from, $to, $branchId)->where('account_id', $account->id)
            ->with('entry')->get()
            ->sortBy(fn ($l) => [$l->entry->date->format('Y-m-d'), $l->journal_entry_id])->values();

        $running = $opening;
        $rows = $lines->map(function (JournalLine $l) use (&$running, $account) {
            $running = round($running + self::normal($account, (float) $l->debit, (float) $l->credit), 2);

            return [
                'date' => $l->entry->date->toDateString(),
                'voucher_no' => $l->entry->voucher_no,
                'event' => $l->entry->event,
                'narration' => $l->entry->narration,
                'memo' => $l->memo,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'balance' => $running,
            ];
        });

        return [
            'account' => $account->only(['id', 'code', 'name', 'type', 'normal_balance']),
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'opening' => $opening,
            'rows' => $rows,
            'total_debit' => round($rows->sum('debit'), 2),
            'total_credit' => round($rows->sum('credit'), 2),
            'closing' => $running,
        ];
    }

    /** Day book: every entry posted on a date. */
    public function dayBook(Carbon $date, ?int $branchId = null): Collection
    {
        return JournalEntry::with('lines.account')->whereDate('date', $date)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')->get()
            ->map(fn (JournalEntry $e) => [
                'voucher_no' => $e->voucher_no, 'event' => $e->event, 'narration' => $e->narration, 'status' => $e->status,
                'lines' => $e->lines->map(fn ($l) => ['account' => $l->account->only(['code', 'name']), 'debit' => (float) $l->debit, 'credit' => (float) $l->credit]),
            ]);
    }

    /** Income statement (P&L) for a period, grouped under the income/expense tree. */
    public function incomeStatement(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $sums = $this->sums($from, $to, $branchId);
        $section = function (string $type) use ($sums) {
            $rows = Account::where('type', $type)->where('is_group', false)->orderBy('code')->with('parent')->get()
                ->map(function (Account $a) use ($sums, $type) {
                    $s = $sums[$a->id] ?? null;
                    // Contra-income (discount allowed) reduces income: always shown in the section's direction.
                    $amount = $s ? round($type === 'income' ? (float) $s->cr - (float) $s->dr : (float) $s->dr - (float) $s->cr, 2) : 0;

                    return ['account' => $a->only(['id', 'code', 'name']), 'group' => $a->parent?->name, 'amount' => $amount];
                })
                ->filter(fn ($r) => $r['amount'] != 0)->values();

            return ['rows' => $rows, 'total' => round($rows->sum('amount'), 2)];
        };

        $income = $section('income');
        $expense = $section('expense');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'income' => $income, 'expense' => $expense,
            'net_profit' => round($income['total'] - $expense['total'], 2),
        ];
    }

    /** Balance sheet at a date: assets = liabilities + equity (+ profit not yet closed to retained earnings). */
    public function balanceSheet(Carbon $asOf, ?int $branchId = null): array
    {
        $sums = $this->sums(null, $asOf, $branchId);
        $section = function (string $type) use ($sums) {
            $rows = Account::where('type', $type)->where('is_group', false)->orderBy('code')->get()
                ->map(fn (Account $a) => [
                    'account' => $a->only(['id', 'code', 'name']),
                    // Contra accounts (accumulated depreciation, drawings) show as negatives in their section.
                    'amount' => isset($sums[$a->id])
                        ? round($type === 'asset' ? (float) $sums[$a->id]->dr - (float) $sums[$a->id]->cr : (float) $sums[$a->id]->cr - (float) $sums[$a->id]->dr, 2)
                        : 0,
                ])
                ->filter(fn ($r) => $r['amount'] != 0)->values();

            return ['rows' => $rows, 'total' => round($rows->sum('amount'), 2)];
        };

        $pl = $this->incomeStatement(Carbon::create(2000), $asOf, $branchId);
        $assets = $section('asset');
        $liabilities = $section('liability');
        $equity = $section('equity');
        $equityTotal = round($equity['total'] + $pl['net_profit'], 2);

        return [
            'as_of' => $asOf->toDateString(),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => [...$equity, 'profit_to_date' => $pl['net_profit'], 'total' => $equityTotal],
            'liabilities_and_equity' => round($liabilities['total'] + $equityTotal, 2),
            'balanced' => abs($assets['total'] - ($liabilities['total'] + $equityTotal)) < 0.01,
        ];
    }

    /** Accounts dashboard figures (Accounts §১৫). */
    public function dashboard(?int $branchId = null): array
    {
        $balances = $this->sums(null, today(), $branchId);
        $money = Account::whereIn('subtype', ['cash', 'bank', 'mfs'])->where('is_group', false)->orderBy('code')->get()
            ->map(fn (Account $a) => [
                'account' => $a->only(['id', 'code', 'name', 'subtype']),
                'balance' => isset($balances[$a->id]) ? round((float) $balances[$a->id]->dr - (float) $balances[$a->id]->cr, 2) : 0,
            ]);
        $receivable = Account::where('system_key', 'receivable')->first();
        $month = $this->incomeStatement(today()->startOfMonth(), today(), $branchId);
        $today = $this->incomeStatement(today(), today(), $branchId);

        return [
            'money' => $money,
            'receivable' => $receivable && isset($balances[$receivable->id]) ? round((float) $balances[$receivable->id]->dr - (float) $balances[$receivable->id]->cr, 2) : 0,
            'today' => ['income' => $today['income']['total'], 'expense' => $today['expense']['total']],
            'month' => ['income' => $month['income']['total'], 'expense' => $month['expense']['total'], 'profit' => $month['net_profit']],
            'months' => collect(range(5, 0))->map(function ($i) use ($branchId) {
                $start = today()->startOfMonth()->subMonths($i);
                $pl = $this->incomeStatement($start, $start->copy()->endOfMonth(), $branchId);

                return ['month' => $start->format('M Y'), 'income' => $pl['income']['total'], 'expense' => $pl['expense']['total']];
            })->values(),
        ];
    }
}
