<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\VendorBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Accounts menu lists (Sprint 16): all vendor bills, and bank / bKash / cash accounts with balances. */
class AccountListsController extends Controller
{
    /** Bills / Payables across vendors: open, overdue or all. */
    public function bills(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $status = $request->input('status', 'open');

        $page = VendorBill::with('vendor:id,name')
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid']))
            ->when($status === 'overdue', fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid'])->whereDate('due_date', '<', today()))
            ->when(in_array($status, ['paid', 'void'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("status IN ('unpaid','partially_paid') DESC")->orderBy('due_date')->paginate(30);
        $open = VendorBill::when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->whereIn('status', ['unpaid', 'partially_paid'])->get(['total', 'paid', 'due_date']);

        return response()->json([
            'data' => collect($page->items())->map(fn (VendorBill $b) => [
                'id' => $b->id, 'no' => $b->bill_no, 'vendor_ref' => $b->vendor_ref, 'vendor' => $b->vendor->only(['id', 'name']),
                'date' => $b->date->toDateString(), 'due_date' => $b->due_date?->toDateString(), 'total' => (float) $b->total, 'paid' => (float) $b->paid,
                'due' => $b->due(), 'status' => $b->status, 'overdue' => in_array($b->status, ['unpaid', 'partially_paid'], true) && $b->due_date?->lt(today()),
                'description' => $b->description,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'summary' => [
                'owed' => round($open->sum(fn ($b) => (float) $b->total - (float) $b->paid), 2),
                'overdue' => round($open->filter(fn ($b) => $b->due_date?->lt(today()))->sum(fn ($b) => (float) $b->total - (float) $b->paid), 2),
            ],
        ]);
    }

    /** Bank Accounts: every cash box, bank and bKash/Nagad account with its book balance and last reconciliation. */
    public function moneyAccounts(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $accounts = Account::whereIn('subtype', ['cash', 'bank', 'mfs'])->where('is_group', false)
            ->when($branches !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhereIn('branch_id', $branches)))
            ->with('branch:id,name')->orderBy('code')->get();
        $sums = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $accounts->pluck('id'))
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) as balance, MAX(journal_entries.date) as last_at')
            ->groupBy('journal_lines.account_id')->get()->keyBy('account_id');
        $recs = BankReconciliation::whereIn('account_id', $accounts->pluck('id'))->where('status', 'completed')
            ->selectRaw('account_id, MAX(statement_date) as d')->groupBy('account_id')->pluck('d', 'account_id');

        return response()->json(['data' => $accounts->map(fn (Account $a) => [
            'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'name_bn' => $a->name_bn, 'kind' => $a->subtype, 'is_active' => $a->is_active,
            'branch' => $a->branch?->name, 'balance' => round((float) ($sums[$a->id]->balance ?? 0), 2),
            'last_activity' => $sums[$a->id]->last_at ?? null, 'reconciled_to' => $recs[$a->id] ?? null,
        ])]);
    }

    /** Adds a bank or bKash/Nagad account under its group with the next free code (1132, 1143 …). */
    public function storeMoneyAccount(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['bank', 'mfs'])],
            'name' => ['required', 'string', 'max:120', 'unique:accounts,name'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $group = Account::where('code', $data['kind'] === 'bank' ? '1130' : '1140')->firstOrFail();
        $next = (int) (Account::where('parent_id', $group->id)->max('code') ?: $group->code) + 1;
        if ($next >= (int) $group->code + 10) {
            throw ValidationException::withMessages(['name' => 'No free account code left in this group — add it from Chart of Accounts instead.']);
        }

        $account = Account::create([
            'code' => (string) $next, 'name' => $data['name'], 'name_bn' => $data['name_bn'] ?? null, 'type' => 'asset', 'normal_balance' => 'debit',
            'parent_id' => $group->id, 'is_group' => false, 'subtype' => $data['kind'], 'branch_id' => $data['branch_id'] ?? null,
            'is_system' => false, 'is_active' => true,
        ]);

        return response()->json(['data' => $account], 201);
    }
}
