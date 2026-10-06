<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only view of the books while Accounts A is built: chart of accounts with balances,
 * and the journal that billing posts to automatically.
 */
class LedgerController extends Controller
{
    /** GET /accounts/chart?branch_id= — every account with its balance (normal side positive). */
    public function chart(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branchId = $request->integer('branch_id') ?: null;

        $sums = JournalLine::query()
            ->whereHas('entry', fn ($q) => $q->where('status', '!=', 'draft'))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->select('account_id', DB::raw('SUM(debit) as dr'), DB::raw('SUM(credit) as cr'))
            ->groupBy('account_id')->get()->keyBy('account_id');

        $accounts = Account::orderBy('code')->get();
        $balance = function (Account $a) use ($sums) {
            $s = $sums[$a->id] ?? null;
            $net = ($s ? (float) $s->dr - (float) $s->cr : 0) + ($a->normal_balance === 'debit' ? 1 : -1) * (float) $a->opening_balance;

            return round($a->normal_balance === 'debit' ? $net : -$net, 2);
        };
        $own = $accounts->mapWithKeys(fn ($a) => [$a->id => $balance($a)]);
        // Groups show the total of their descendants (in their own normal direction).
        $total = function (Account $a) use (&$total, $accounts, $own): float {
            $children = $accounts->where('parent_id', $a->id);
            if ($children->isEmpty()) {
                return $own[$a->id];
            }

            return round($children->sum(fn ($c) => ($c->normal_balance === $a->normal_balance ? 1 : -1) * $total($c)), 2);
        };

        $totals = ['debit' => round((float) $sums->sum('dr'), 2), 'credit' => round((float) $sums->sum('cr'), 2)];

        return response()->json(['data' => $accounts->map(fn (Account $a) => [
            ...$a->only(['id', 'code', 'name', 'name_bn', 'type', 'parent_id', 'is_group', 'normal_balance', 'subtype', 'is_system', 'is_active']),
            'balance' => $a->is_group ? $total($a) : $own[$a->id],
        ]), 'totals' => $totals]);
    }

    /** GET /accounts/journal?event=&account_id=&from=&to= */
    public function journal(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $entries = JournalEntry::with(['lines.account', 'branch', 'preparer'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', $request->string('event').'%'))
            ->when($request->filled('account_id'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $request->integer('account_id'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'data' => collect($entries->items())->map(fn (JournalEntry $e) => [
                ...$e->only(['id', 'voucher_no', 'voucher_type', 'event', 'narration', 'status', 'reversal_of_id']),
                'date' => $e->date->toDateString(),
                'branch' => $e->branch?->only(['id', 'name']),
                'prepared_by' => $e->preparer?->name,
                'lines' => $e->lines->map(fn ($l) => [
                    'account' => $l->account->only(['id', 'code', 'name']),
                    'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'memo' => $l->memo,
                ]),
            ]),
            'meta' => ['current_page' => $entries->currentPage(), 'last_page' => $entries->lastPage(), 'total' => $entries->total()],
        ]);
    }
}
