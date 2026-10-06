<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Services\FinancialReportService;
use App\Services\FixedAssetService;
use App\Services\LedgerService;
use App\Services\ReconciliationService;
use App\Services\YearEndService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Accounts C: bank reconciliation, fixed assets, budgets and year-end closing. */
class AssetsBankBudgetController extends Controller
{
    // ---- Bank reconciliation ----------------------------------------------------------------------

    public function reconciliations(): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);

        return response()->json([
            'data' => BankReconciliation::with('account')->withCount(['lines', 'lines as unmatched_count' => fn ($q) => $q->where('status', 'unmatched')])
                ->latest('statement_date')->limit(50)->get()->map(fn ($r) => [
                    ...$r->only(['id', 'status', 'lines_count', 'unmatched_count']),
                    'statement_date' => $r->statement_date->toDateString(), 'statement_balance' => (float) $r->statement_balance,
                    'account' => $r->account->only(['id', 'code', 'name']),
                ]),
            'accounts' => ReconciliationService::moneyAccounts(),
        ]);
    }

    public function storeReconciliation(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->whereIn('subtype', ['bank', 'mfs'])],
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_balance' => ['required', 'numeric'],
        ]);
        if (BankReconciliation::where('account_id', $data['account_id'])->where('status', 'draft')->exists()) {
            abort(422, 'Finish the open reconciliation for this account first.');
        }

        return response()->json(['data' => BankReconciliation::create([...$data, 'status' => 'draft', 'created_by' => $request->user()->id])], 201);
    }

    public function reconciliation(BankReconciliation $rec, ReconciliationService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);

        return response()->json(['data' => $this->presentRec($rec, $service)]);
    }

    /** POST /accounts/reconciliations/{id}/{action} — import | lines | auto-match | complete */
    public function reconcileAction(Request $request, BankReconciliation $rec, string $action, ReconciliationService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $message = match ($action) {
            'import' => $service->import($rec, (string) file_get_contents($request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']])['file']->getRealPath())).' lines imported.',
            'lines' => (function () use ($request, $rec) {
                $data = $request->validate(['date' => ['required', 'date'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'not_in:0'], 'reference' => ['nullable', 'string', 'max:100']]);
                abort_if($rec->status !== 'draft', 422, 'This reconciliation is completed.');
                $rec->lines()->create([...$data, 'status' => 'unmatched']);

                return 'Line added.';
            })(),
            'auto-match' => $service->autoMatch($rec).' lines matched.',
            'complete' => ($service->complete($rec, $request->user()) ? 'Reconciliation completed.' : ''),
        };

        return response()->json(['message' => $message, 'data' => $this->presentRec($rec->refresh(), $service)]);
    }

    /** POST /accounts/statement-lines/{line}/{action} — match (journal_line_id or null) | adjust | delete */
    public function lineAction(Request $request, BankStatementLine $line, string $action, ReconciliationService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        match ($action) {
            'match' => $service->match($line, $request->validate(['journal_line_id' => ['nullable', 'integer']])['journal_line_id'] ?? null),
            'adjust' => $service->adjust($line, $request->user()),
            'delete' => abort_if(BankReconciliation::find($line->bank_reconciliation_id)->status !== 'draft' || $line->status === 'adjusted', 422, 'This line cannot be removed.') ?? $line->delete(),
        };

        return response()->json(['data' => $this->presentRec(BankReconciliation::findOrFail($line->bank_reconciliation_id), $service)]);
    }

    private function presentRec(BankReconciliation $rec, ReconciliationService $service): array
    {
        $rec->load(['account', 'lines.journalLine.entry']);

        return [
            ...$rec->only(['id', 'status']),
            'statement_date' => $rec->statement_date->toDateString(),
            'statement_balance' => (float) $rec->statement_balance,
            'account' => $rec->account->only(['id', 'code', 'name']),
            'lines' => $rec->lines->map(fn (BankStatementLine $l) => [
                ...$l->only(['id', 'description', 'reference', 'status']),
                'date' => $l->date->toDateString(), 'amount' => (float) $l->amount,
                'matched' => $l->journalLine ? ['voucher_no' => $l->journalLine->entry->voucher_no, 'date' => $l->journalLine->entry->date->toDateString(), 'narration' => $l->journalLine->entry->narration] : null,
            ]),
            'summary' => $service->summary($rec),
        ];
    }

    // ---- Fixed assets -----------------------------------------------------------------------------

    public function assets(): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $assets = FixedAsset::with(['account', 'branch'])->orderByRaw("status = 'active' desc")->orderBy('asset_code')->get();

        return response()->json([
            'data' => $assets->map(fn (FixedAsset $a) => [
                ...$a->only(['id', 'asset_code', 'name', 'location', 'useful_life_months', 'depreciated_until', 'status', 'notes']),
                'purchase_date' => $a->purchase_date->toDateString(), 'disposed_at' => $a->disposed_at?->toDateString(),
                'cost' => (float) $a->cost, 'salvage_value' => (float) $a->salvage_value, 'accumulated_depreciation' => (float) $a->accumulated_depreciation,
                'book_value' => $a->bookValue(), 'monthly_depreciation' => $a->monthlyDepreciation(),
                'category' => $a->account->only(['id', 'code', 'name']), 'branch' => $a->branch->only(['id', 'name']),
            ]),
            'categories' => Account::where('subtype', 'fixed_asset')->where('is_group', false)->orderBy('code')->get(['id', 'code', 'name']),
            'totals' => ['cost' => round($assets->where('status', 'active')->sum('cost'), 2), 'book_value' => round($assets->where('status', 'active')->sum(fn ($a) => $a->bookValue()), 2)],
        ]);
    }

    public function storeAsset(Request $request, FixedAssetService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['required', 'numeric', 'min:1'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lt:cost'],
            'useful_life_months' => ['required', 'integer', 'between:1,600'],
            'paid_from_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereIn('subtype', ['cash', 'bank', 'mfs'])],
            'accumulated_depreciation' => ['nullable', 'numeric', 'min:0', 'prohibited_unless:paid_from_account_id,null'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        return response()->json(['data' => $service->register([...$data, 'salvage_value' => $data['salvage_value'] ?? 0], $request->user())], 201);
    }

    public function depreciate(Request $request, FixedAssetService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.today()->format('Y-m')]]);

        return response()->json(['data' => $service->depreciate(Carbon::createFromFormat('Y-m-d', $data['month'].'-01'), $request->user())]);
    }

    public function disposeAsset(Request $request, FixedAsset $asset, FixedAssetService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_APPROVE);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'received_in_account_id' => ['nullable', 'required_unless:amount,null,0', 'integer', Rule::exists('accounts', 'id')->whereIn('subtype', ['cash', 'bank', 'mfs'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $service->dispose($asset->load('account'), $data, $request->user())]);
    }

    // ---- Budgets ----------------------------------------------------------------------------------

    public function budgets(Request $request, LedgerService $ledger): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        $ledger->openPeriodFor(today()); // make sure this fiscal year exists

        return response()->json([
            'data' => Budget::with(['fiscalYear', 'branch'])->withSum('lines', 'annual_amount')->latest('id')->get()->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'fiscal_year' => $b->fiscalYear->name, 'fiscal_year_id' => $b->fiscal_year_id,
                'branch' => $b->branch?->only(['id', 'name']), 'total' => (float) $b->lines_sum_annual_amount,
            ]),
            'years' => FiscalYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'accounts' => Account::whereIn('type', ['income', 'expense'])->where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function budget(Budget $budget): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);

        return response()->json(['data' => [
            ...$budget->only(['id', 'name', 'fiscal_year_id', 'branch_id']),
            'lines' => $budget->lines()->get()->map(fn ($l) => ['account_id' => $l->account_id, 'annual_amount' => (float) $l->annual_amount]),
        ]]);
    }

    /** PUT /accounts/budgets (create) or /accounts/budgets/{id} — the full list of lines each time. */
    public function saveBudget(Request $request, ?Budget $budget = null): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->whereIn('type', ['income', 'expense'])],
            'lines.*.annual_amount' => ['required', 'numeric', 'min:0'],
        ]);
        $header = collect($data)->except('lines')->all();
        $budget = $budget?->exists ? tap($budget)->update($header) : Budget::create([...$header, 'created_by' => $request->user()->id]);
        $budget->lines()->delete();
        foreach (collect($data['lines'])->filter(fn ($l) => $l['annual_amount'] > 0)->unique('account_id') as $line) {
            $budget->lines()->create($line);
        }

        return response()->json(['data' => $budget], $budget->wasRecentlyCreated ? 201 : 200);
    }

    public function budgetVsActual(Request $request, Budget $budget, FinancialReportService $reports): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_REPORTS);
        $fy = $budget->fiscalYear;
        $from = $request->date('from') ?? $fy->start_date;
        $to = $request->date('to') ?? min(today(), $fy->end_date);

        return response()->json(['data' => [...$reports->budgetVsActual($budget, Carbon::parse($from), Carbon::parse($to)), 'budget' => $budget->only(['id', 'name'])]]);
    }

    // ---- Year end ---------------------------------------------------------------------------------

    public function closeYear(Request $request, FiscalYear $year, YearEndService $service): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PERIOD_CLOSE);

        return response()->json(['data' => $service->close($year, $request->user())]);
    }
}
