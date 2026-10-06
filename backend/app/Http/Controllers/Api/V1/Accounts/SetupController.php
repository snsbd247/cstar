<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\AccountSettings;
use App\Services\PeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Accounts settings: chart of accounts maintenance, month locking and the approval limit. */
class SetupController extends Controller
{
    public function storeAccount(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $this->validated($request);
        $parent = Account::findOrFail($data['parent_id']);
        if (! $parent->is_group) {
            throw ValidationException::withMessages(['parent_id' => 'Choose a group as the parent.']);
        }

        $account = Account::create([
            ...$data,
            'type' => $parent->type,
            'normal_balance' => in_array($parent->type, ['asset', 'expense'], true) ? 'debit' : 'credit',
            'is_system' => false,
        ]);

        return response()->json(['data' => $account], 201);
    }

    /** System accounts keep their code and type; only names and the active flag change. */
    public function updateAccount(Request $request, Account $account): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_COA_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        if ($account->is_system && isset($data['is_active']) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'System accounts are used by automatic posting and cannot be turned off.']);
        }
        $account->update($data);

        return response()->json(['data' => $account]);
    }

    public function periods(Request $request, PeriodService $periods): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $date = $request->filled('year') ? Carbon::create($request->integer('year'), 7, 1) : today();

        $months = $periods->year($date);
        $year = \App\Models\FiscalYear::findOrFail($months->first()->fiscal_year_id);

        return response()->json([
            'data' => $months->map(fn (AccountingPeriod $p) => [
                'id' => $p->id, 'label' => $p->start_date->format('F Y'), 'status' => $p->status,
                'start_date' => $p->start_date->toDateString(), 'end_date' => $p->end_date->toDateString(), 'closed_at' => $p->closed_at,
            ]),
            'year' => [...$year->only(['id', 'name', 'status', 'closed_at']), 'start_date' => $year->start_date->toDateString(), 'end_date' => $year->end_date->toDateString()],
            'years' => FiscalYear::orderByDesc('start_date')->get(['id', 'name', 'status'])->map(fn ($y) => [...$y->only(['id', 'name', 'status']), 'start_year' => (int) substr($y->name, 0, 4)]),
        ]);
    }

    public function closePeriod(Request $request, AccountingPeriod $period, PeriodService $periods): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PERIOD_CLOSE);

        return response()->json(['data' => $periods->close($period, $request->user())]);
    }

    public function reopenPeriod(Request $request, AccountingPeriod $period, PeriodService $periods): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PERIOD_CLOSE);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['data' => $periods->reopen($period, $request->user(), $data['reason'])]);
    }

    public function settings(AccountSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);

        return response()->json(['data' => $settings->all()]);
    }

    public function updateSettings(Request $request, AccountSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $data = $request->validate(['approval_limit' => ['required', 'numeric', 'min:0', 'max:10000000']]);
        $settings->update(['approval_limit' => (string) $data['approval_limit']]);

        return response()->json(['data' => $settings->all()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^\d{4,6}$/', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['required', 'integer', 'exists:accounts,id'],
            'is_group' => ['boolean'],
            'subtype' => ['nullable', Rule::in(['cash', 'bank', 'mfs'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
    }
}
