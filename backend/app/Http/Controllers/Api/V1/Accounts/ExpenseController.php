<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\AccountMap;
use App\Services\ExpenseService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Expenses, their categories, recurring expenses and the "paid from" choices (Accounts §৫). */
class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_EXPENSE_CREATE);
        $user = $request->user();
        $branches = $user->accessibleBranchIds();

        $page = Expense::with(['category', 'branch', 'paidFrom', 'creator', 'voucher'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            // Front-desk staff see their own expenses; accounts staff see the branch.
            ->when(! $user->can(Permission::ACCOUNTS_VIEW), fn ($q) => $q->where('created_by', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('category_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (Expense $e) => [
                ...$e->only(['id', 'expense_no', 'payee', 'reference', 'description', 'status', 'voucher_id']),
                'date' => $e->date->toDateString(),
                'amount' => (float) $e->amount,
                'category' => $e->category->only(['id', 'name', 'name_bn']),
                'branch' => $e->branch->only(['id', 'name']),
                'paid_from' => $e->paidFrom->only(['id', 'code', 'name']),
                'created_by' => $e->creator?->only(['id', 'name']),
                'voucher_no' => $e->voucher?->voucher_no,
                'reject_reason' => $e->voucher?->reject_reason,
                'has_attachment' => (bool) $e->attachment_path,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'total' => round((float) (clone $page->getCollection())->sum('amount'), 2),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_EXPENSE_CREATE);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'paid_from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('expenses', 'local');
        }
        $expense = $this->expenses->create($data, $request->user());

        return response()->json(['data' => [...$expense->only(['id', 'expense_no', 'status']), 'amount' => (float) $expense->amount]], 201);
    }

    /** Post a draft (e.g. a recurring expense raised this month). */
    public function submit(Request $request, Expense $expense, VoucherService $vouchers): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);
        $vouchers->submit($expense->voucher, $request->user());

        return response()->json(['data' => $expense->refresh()->only(['id', 'expense_no', 'status'])]);
    }

    public function attachment(Request $request, Expense $expense): StreamedResponse
    {
        Gate::authorize(Permission::ACCOUNTS_EXPENSE_CREATE);
        abort_unless($expense->attachment_path && $request->user()->canAccessBranch($expense->branch_id), 404);

        return Storage::disk('local')->response($expense->attachment_path);
    }

    /** GET /accounts/expense-options?branch_id= — categories and where the money can come from. */
    public function options(Request $request, AccountMap $map): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_EXPENSE_CREATE);
        $branchId = $request->integer('branch_id') ?: $request->user()->branches->first()?->id;
        if ($branchId) {
            abort_unless($request->user()->canAccessBranch($branchId), 403);
            $map->cashFor($branchId);
        }
        $frontDesk = ! $request->user()->can(Permission::ACCOUNTS_VOUCHER_CREATE);

        $paidFrom = Account::whereIn('subtype', $frontDesk ? ['cash'] : ['cash', 'bank', 'mfs'])
            ->where('is_group', false)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderByRaw('branch_id is null')->orderBy('code')->get(['id', 'code', 'name', 'subtype', 'branch_id']);

        return response()->json(['data' => [
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_bn', 'account_id']),
            'paid_from' => $paidFrom,
        ]]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('type', 'expense')->where('is_group', false)],
        ]);

        return response()->json(['data' => ExpenseCategory::create($data)], 201);
    }

    public function recurring(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        return response()->json(['data' => RecurringExpense::with(['category', 'branch', 'paidFrom'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->orderBy('day_of_month')->get()
            ->map(fn (RecurringExpense $r) => [
                ...$r->only(['id', 'day_of_month', 'payee', 'description', 'is_active', 'last_generated_period', 'expense_category_id', 'paid_from_account_id', 'branch_id']),
                'amount' => (float) $r->amount,
                'category' => $r->category->only(['id', 'name']),
                'branch' => $r->branch->only(['id', 'name']),
                'paid_from' => $r->paidFrom->only(['id', 'code', 'name']),
            ])]);
    }

    public function saveRecurring(Request $request, ?RecurringExpense $recurring = null): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'day_of_month' => ['required', 'integer', 'between:1,28'],
            'paid_from_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->whereIn('subtype', ['cash', 'bank', 'mfs'])],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        $recurring?->exists
            ? $recurring->update($data)
            : $recurring = RecurringExpense::create([...$data, 'created_by' => $request->user()->id]);

        return response()->json(['data' => $recurring], $recurring->wasRecentlyCreated ? 201 : 200);
    }
}
