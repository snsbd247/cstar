<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\AccountSettings;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Manual vouchers PV / RV / JV / CV with maker-checker approval (Accounts §৪). */
class VoucherController extends Controller
{
    public function __construct(private VoucherService $vouchers) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $page = Voucher::with(['branch', 'preparer', 'approver', 'lines.account'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('voucher_no', 'like', '%'.$request->string('q').'%')->orWhere('narration', 'like', '%'.$request->string('q').'%')))
            ->orderByRaw("status = 'submitted' desc")->latest('date')->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (Voucher $v) => $this->present($v, $request)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'approval_limit' => app(AccountSettings::class)->approvalLimit(),
        ]);
    }

    public function show(Request $request, Voucher $voucher): JsonResponse
    {
        $this->authorizeView($request, $voucher);

        return response()->json(['data' => $this->present($voucher->load(['branch', 'preparer', 'approver', 'lines.account', 'journalEntry']), $request)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $this->validated($request);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        $voucher = $this->vouchers->create($data, $request->user());
        if (! empty($data['submit'])) {
            $voucher = $this->vouchers->submit($voucher, $request->user());
        }

        return response()->json(['data' => $this->present($voucher->load(['branch', 'preparer', 'approver', 'lines.account']), $request)], 201);
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $this->authorizeView($request, $voucher);
        $data = $this->validated($request, $voucher->type);

        $voucher = $this->vouchers->update($voucher->load('lines'), $data);
        if (! empty($data['submit'])) {
            $voucher = $this->vouchers->submit($voucher, $request->user());
        }

        return response()->json(['data' => $this->present($voucher->load(['branch', 'preparer', 'approver', 'lines.account']), $request)]);
    }

    public function destroy(Request $request, Voucher $voucher): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $this->authorizeView($request, $voucher);
        $this->vouchers->deleteDraft($voucher);

        return response()->json(['message' => 'Deleted.']);
    }

    /** POST /accounts/vouchers/{id}/{action} — submit | approve | reject | reverse */
    public function action(Request $request, Voucher $voucher, string $action): JsonResponse
    {
        $this->authorizeView($request, $voucher);
        $user = $request->user();
        $voucher = match ($action) {
            'submit' => (Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE) ? $this->vouchers->submit($voucher, $user) : null),
            'approve' => (Gate::authorize(Permission::ACCOUNTS_VOUCHER_APPROVE) ? $this->vouchers->approve($voucher, $user) : null),
            'reject' => (Gate::authorize(Permission::ACCOUNTS_VOUCHER_APPROVE) ? $this->vouchers->reject($voucher, $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'], $user) : null),
            'reverse' => (Gate::authorize(Permission::ACCOUNTS_VOUCHER_APPROVE) ? $this->vouchers->reverse($voucher->load('journalEntry'), $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'], $user) : null),
        };

        return response()->json(['data' => $this->present($voucher->load(['branch', 'preparer', 'approver', 'lines.account']), $request)]);
    }

    private function validated(Request $request, ?string $type = null): array
    {
        return $request->validate([
            'type' => [$type ? 'prohibited' : 'required', Rule::in(array_keys(Voucher::TYPES))],
            'date' => ['required', 'date'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'narration' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
            'submit' => ['boolean'],
        ]);
    }

    private function present(Voucher $v, Request $request): array
    {
        $user = $request->user();
        $limit = app(AccountSettings::class)->approvalLimit();

        return [
            ...$v->only(['id', 'voucher_no', 'type', 'narration', 'status', 'reject_reason']),
            'date' => $v->date->toDateString(),
            'amount' => (float) $v->amount,
            'branch' => $v->branch?->only(['id', 'name']),
            'prepared_by' => $v->preparer?->only(['id', 'name']),
            'approved_by' => $v->approver?->only(['id', 'name']),
            'journal_no' => $v->relationLoaded('journalEntry') ? $v->journalEntry?->voucher_no : null,
            'needs_approval' => (float) $v->amount > $limit,
            'lines' => $v->lines->map(fn ($l) => [
                'account_id' => $l->account_id, 'account' => $l->account->only(['id', 'code', 'name']),
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'memo' => $l->memo,
            ]),
            'can' => [
                'edit' => in_array($v->status, ['draft', 'rejected'], true) && $user->can(Permission::ACCOUNTS_VOUCHER_CREATE),
                'approve' => $v->status === 'submitted' && $user->can(Permission::ACCOUNTS_VOUCHER_APPROVE) && ($v->prepared_by !== $user->id || $user->isSuperAdmin()),
                'reverse' => $v->status === 'posted' && $user->can(Permission::ACCOUNTS_VOUCHER_APPROVE),
            ],
        ];
    }

    private function authorizeView(Request $request, Voucher $voucher): void
    {
        abort_unless(($request->user()->can(Permission::ACCOUNTS_VIEW) || $voucher->prepared_by === $request->user()->id)
            && $request->user()->canAccessBranch($voucher->branch_id), 403);
    }
}
