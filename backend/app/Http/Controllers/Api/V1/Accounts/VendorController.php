<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\PayablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Vendors, their bills and payments, and how long money has been owed (Accounts §৬). */
class VendorController extends Controller
{
    public function __construct(private PayablesService $payables) {}

    /** GET /accounts/vendors — with what is owed and its age. */
    public function index(): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);

        $vendors = Vendor::with(['bills' => fn ($q) => $q->whereIn('status', ['unpaid', 'partially_paid'])])->orderBy('name')->get();

        return response()->json(['data' => $vendors->map(fn (Vendor $v) => [
            ...$v->only(['id', 'name', 'type', 'phone', 'address', 'is_active', 'notes']),
            'opening_balance' => (float) $v->opening_balance,
            'balance' => $this->payables->balance($v),
            'aging' => $this->aging($v),
        ]), 'total' => round($vendors->sum(fn ($v) => $this->payables->balance($v)), 2)]);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VIEW);
        $vendor->load(['bills.items.account', 'payments.paidFrom', 'payments.allocations.bill']);

        return response()->json(['data' => [
            ...$vendor->only(['id', 'name', 'type', 'phone', 'address', 'is_active', 'notes']),
            'opening_balance' => (float) $vendor->opening_balance,
            'balance' => $this->payables->balance($vendor),
            'bills' => $vendor->bills->sortByDesc('date')->values()->map(fn (VendorBill $b) => [
                ...$b->only(['id', 'bill_no', 'vendor_ref', 'status', 'description']),
                'date' => $b->date->toDateString(), 'due_date' => $b->due_date?->toDateString(),
                'total' => (float) $b->total, 'paid' => (float) $b->paid, 'due' => $b->due(),
                'items' => $b->items->map(fn ($i) => ['account' => $i->account->only(['code', 'name']), 'description' => $i->description, 'amount' => (float) $i->amount]),
            ]),
            'payments' => $vendor->payments->sortByDesc('date')->values()->map(fn ($p) => [
                'id' => $p->id, 'payment_no' => $p->payment_no, 'date' => $p->date->toDateString(), 'amount' => (float) $p->amount,
                'paid_from' => $p->paidFrom->name, 'reference' => $p->reference, 'bills' => $p->allocations->map(fn ($a) => $a->bill->bill_no)->all(),
            ]),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $this->validated($request);

        return response()->json(['data' => $this->payables->createVendor($data, $request->user())], 201);
    }

    /** Opening balance is set once at creation (it was posted to the books). */
    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $vendor->update(collect($this->validated($request))->except('opening_balance')->all());

        return response()->json(['data' => $vendor]);
    }

    public function storeBill(Request $request, Vendor $vendor): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'vendor_ref' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'min:1'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        return response()->json(['data' => $this->payables->createBill($vendor, $data, $request->user())], 201);
    }

    public function voidBill(Request $request, VendorBill $bill): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_APPROVE);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['data' => $this->payables->voidBill($bill, $data['reason'])]);
    }

    public function pay(Request $request, Vendor $vendor): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_VOUCHER_CREATE);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        return response()->json(['data' => $this->payables->pay($vendor, $data, $request->user())], 201);
    }

    /** Unpaid bills by how long they are past their due date. */
    private function aging(Vendor $v): array
    {
        $buckets = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90p' => 0.0];
        foreach ($v->bills as $b) {
            $late = $b->due_date && $b->due_date->isPast() ? (int) $b->due_date->diffInDays(today()) : 0;
            $key = $late === 0 ? 'current' : ($late <= 30 ? 'd30' : ($late <= 60 ? 'd60' : ($late <= 90 ? 'd90' : 'd90p')));
            $buckets[$key] = round($buckets[$key] + $b->due(), 2);
        }

        return $buckets;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Vendor::TYPES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
