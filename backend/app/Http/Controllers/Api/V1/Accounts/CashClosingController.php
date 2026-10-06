<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CashClosing;
use App\Services\CashClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** "Close My Cash" and the supervisor's hand-over (Accounts §৮). */
class CashClosingController extends Controller
{
    public function __construct(private CashClosingService $closings) {}

    /** GET /accounts/cash-closings?date= — mine, or the whole branch for supervisors. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_CASH_CLOSING);
        $user = $request->user();
        $branches = $user->accessibleBranchIds();
        $supervisor = $user->can(Permission::ACCOUNTS_VIEW);

        $rows = CashClosing::with(['user', 'branch', 'receiver'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when(! $supervisor, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->date('date')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('date')->latest('id')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn (CashClosing $c) => $this->present($c, $user))]);
    }

    /** GET /accounts/cash-closings/expected?date=&branch_id= — what my drawer should hold. */
    public function expected(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_CASH_CLOSING);
        $branchId = $request->integer('branch_id') ?: $request->user()->branches->first()?->id;
        abort_unless($branchId && $request->user()->canAccessBranch($branchId), 403);
        $date = $request->date('date') ?? today();

        return response()->json(['data' => [
            ...$this->closings->expected($request->user(), $branchId, $date),
            'date' => $date->toDateString(),
            'branch_id' => $branchId,
            'already_closed' => CashClosing::where('user_id', $request->user()->id)->where('branch_id', $branchId)->whereDate('date', $date)->exists(),
            'notes' => CashClosingService::NOTES,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_CASH_CLOSING);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'counted_cash' => ['required_without:denominations', 'nullable', 'numeric', 'min:0'],
            'denominations' => ['nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        $closing = $this->closings->close($request->user(), $data);

        return response()->json(['data' => $this->present($closing->load(['user', 'branch', 'receiver']), $request->user())], 201);
    }

    public function receive(Request $request, CashClosing $closing): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        abort_unless($request->user()->canAccessBranch($closing->branch_id), 403);

        return response()->json(['data' => $this->present($this->closings->receive($closing, $request->user())->load(['user', 'branch', 'receiver']), $request->user())]);
    }

    private function present(CashClosing $c, $user): array
    {
        return [
            ...$c->only(['id', 'status', 'reason', 'expected', 'denominations']),
            'date' => $c->date->toDateString(),
            'expected_cash' => (float) $c->expected_cash,
            'counted_cash' => (float) $c->counted_cash,
            'difference' => (float) $c->difference,
            'user' => $c->user->only(['id', 'name']),
            'branch' => $c->branch->only(['id', 'name']),
            'received_by' => $c->receiver?->only(['id', 'name']),
            'received_at' => $c->received_at,
            'can_receive' => $c->status === 'submitted' && $user->can(Permission::ACCOUNTS_VIEW) && ($c->user_id !== $user->id || $user->isSuperAdmin()),
        ];
    }
}
