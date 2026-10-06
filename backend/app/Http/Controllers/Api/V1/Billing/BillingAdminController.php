<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PackageUsage;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\AccountMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Billing & Payments and Packages menu pages (Sprint 16): billing dashboard, receipts, refunds, payment
 * allocations, discounts and package usage. Money is still taken and refunded from the child's Billing tab.
 * Receptionists see only the payments they received, as on the collection screen.
 */
class BillingAdminController extends Controller
{
    public function dashboard(Request $request, AccountMap $accounts): JsonResponse
    {
        abort_unless($request->user()->can(Permission::INVOICES_VIEW) || $request->user()->can(Permission::PAYMENTS_VIEW), 403);
        $user = $request->user();
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $sign = fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;
        $month = $this->paymentScope($user)->where('status', 'completed')->whereBetween('paid_at', [$monthStart, $today->copy()->endOfDay()])->get(['type', 'amount', 'method', 'paid_at']);
        $invoices = $this->invoices($user)->whereNotIn('status', ['draft', 'void']);
        $dueRows = (clone $invoices)->where('due_total', '>', 0)->with('patient:id,name,patient_code')->get(['id', 'patient_id', 'due_total', 'due_date']);
        $advance = $accounts->system('patient_advances');
        $branches = $user->accessibleBranchIds();
        $advanceBalance = DB::table('journal_lines')->where('account_id', $advance->id)
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as b')->value('b');

        return response()->json(['data' => [
            'kpis' => [
                'collected_today' => round($month->filter(fn ($p) => $p->paid_at->isToday())->sum($sign), 2),
                'collected_month' => round($month->sum($sign), 2),
                'invoiced_month' => round((float) (clone $invoices)->whereBetween('issue_date', [$monthStart, $today])->sum('total'), 2),
                'outstanding' => round((float) $dueRows->sum('due_total'), 2),
                'overdue_invoices' => $dueRows->filter(fn ($i) => $i->due_date && $i->due_date->lt($today))->count(),
                'advances_held' => round((float) $advanceBalance, 2),
                'discounts_month' => round((float) (clone $invoices)->whereBetween('issue_date', [$monthStart, $today])->sum('discount_total'), 2),
            ],
            'by_method' => collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => round($month->where('method', $m)->sum($sign), 2)]),
            'top_dues' => $dueRows->groupBy('patient_id')->map(fn ($rows) => [
                'patient' => $rows->first()->patient->only(['id', 'name', 'patient_code']), 'due' => round((float) $rows->sum('due_total'), 2), 'invoices' => $rows->count(),
            ])->sortByDesc('due')->take(6)->values(),
            'recent' => $this->paymentScope($user)->with('patient:id,name')->latest('paid_at')->latest('id')->limit(6)->get()
                ->map(fn (Payment $p) => ['id' => $p->id, 'no' => $p->receipt_no, 'type' => $p->type, 'patient' => $p->patient?->name, 'amount' => (float) $p->amount, 'method' => $p->method, 'at' => $p->paid_at->toIso8601String(), 'status' => $p->status]),
        ]]);
    }

    /** Receipts (type = payment) or refunds (type = refund) over a period. */
    public function payments(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_VIEW);
        $type = $request->input('type') === 'refund' ? 'refund' : 'payment';
        $q = trim((string) $request->input('q'));
        $query = $this->paymentScope($request->user())->where('type', $type)
            ->when($request->filled('from'), fn ($p) => $p->where('paid_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($p) => $p->where('paid_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('method'), fn ($p) => $p->where('method', $request->string('method')))
            ->when($request->filled('status'), fn ($p) => $p->where('status', $request->string('status')))
            ->when($q !== '', fn ($p) => $p->where(fn ($w) => $w->where('receipt_no', 'like', "%{$q}%")->orWhere('transaction_ref', 'like', "%{$q}%")
                ->orWhereHas('patient', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%"))));
        $total = (float) (clone $query)->where('status', 'completed')->sum('amount');
        $page = $query->with(['patient:id,name,patient_code', 'receiver:id,name'])->latest('paid_at')->latest('id')->paginate(30);

        return $this->page($page, fn (Payment $p) => [
            'id' => $p->id, 'no' => $p->receipt_no, 'patient' => $p->patient?->only(['id', 'name', 'patient_code']), 'amount' => (float) $p->amount,
            'method' => $p->method, 'transaction_ref' => $p->transaction_ref, 'at' => $p->paid_at->toIso8601String(), 'status' => $p->status,
            'by' => $p->receiver?->name, 'note' => $p->notes, 'void_reason' => $p->void_reason,
        ], ['total' => round($total, 2)]);
    }

    /** Payment Allocations: which payment paid which invoice (and what came from advance). */
    public function allocations(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_VIEW);
        $q = trim((string) $request->input('q'));
        $payments = $this->paymentScope($request->user())->select('id');

        $page = PaymentAllocation::with(['payment:id,receipt_no,paid_at,method,status,patient_id', 'payment.patient:id,name,patient_code', 'invoice:id,invoice_no,total,status'])
            ->whereIn('payment_id', $payments)
            ->when($request->filled('from'), fn ($a) => $a->whereHas('payment', fn ($p) => $p->where('paid_at', '>=', $request->date('from')->startOfDay())))
            ->when($request->filled('to'), fn ($a) => $a->whereHas('payment', fn ($p) => $p->where('paid_at', '<=', $request->date('to')->endOfDay())))
            ->when($q !== '', fn ($a) => $a->where(fn ($w) => $w->whereHas('invoice', fn ($i) => $i->where('invoice_no', 'like', "%{$q}%"))
                ->orWhereHas('payment', fn ($p) => $p->where('receipt_no', 'like', "%{$q}%")->orWhereHas('patient', fn ($c) => $c->where('name', 'like', "%{$q}%")))))
            ->latest('id')->paginate(30);

        return $this->page($page, fn (PaymentAllocation $a) => [
            'id' => $a->id, 'amount' => (float) $a->amount, 'from_advance' => (bool) $a->from_advance,
            'payment' => ['id' => $a->payment->id, 'no' => $a->payment->receipt_no, 'at' => $a->payment->paid_at->toIso8601String(), 'method' => $a->payment->method, 'status' => $a->payment->status],
            'patient' => $a->payment->patient?->only(['id', 'name', 'patient_code']),
            'invoice' => ['id' => $a->invoice->id, 'no' => $a->invoice->invoice_no, 'total' => (float) $a->invoice->total, 'status' => $a->invoice->status],
        ]);
    }

    /** Discounts / Concessions: every issued invoice with a discount, the reason and who gave it. */
    public function discounts(Request $request): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_VIEW);
        $query = $this->invoices($request->user())->where('discount_total', '>', 0)->where('status', '!=', 'draft')
            ->when($request->filled('from'), fn ($i) => $i->whereDate('issue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($i) => $i->whereDate('issue_date', '<=', $request->date('to')));
        $total = (float) (clone $query)->where('status', '!=', 'void')->sum('discount_total');
        $page = $query->with(['patient:id,name,patient_code', 'issuer:id,name'])->latest('issue_date')->latest('id')->paginate(30);

        return $this->page($page, fn (Invoice $i) => [
            'id' => $i->id, 'no' => $i->invoice_no, 'date' => $i->issue_date?->toDateString(), 'patient' => $i->patient->only(['id', 'name', 'patient_code']),
            'subtotal' => (float) $i->subtotal, 'discount' => (float) $i->discount_total, 'percent' => (float) $i->subtotal > 0 ? round((float) $i->discount_total / (float) $i->subtotal * 100, 1) : null,
            'reason' => $i->discount_reason, 'by' => $i->issuer?->name, 'status' => $i->status,
        ], ['total' => round($total, 2)]);
    }

    /** Package Usage: each session taken from a package — by a finalized note, a no-show or a late cancellation. */
    public function packageUsage(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $q = trim((string) $request->input('q'));

        $page = PackageUsage::with(['patientPackage:id,patient_id,package_id,total_sessions,used_sessions,branch_id', 'patientPackage.patient:id,name,patient_code', 'patientPackage.package:id,name', 'appointment:id,date,start_time'])
            ->whereHas('patientPackage', fn ($p) => $p->when($branches !== null, fn ($b) => $b->whereIn('branch_id', $branches))
                ->when($q !== '', fn ($c) => $c->whereHas('patient', fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('patient_code', 'like', "%{$q}%"))))
            ->when($request->filled('reason'), fn ($u) => $u->where('reason', $request->string('reason')))
            ->when($request->filled('from'), fn ($u) => $u->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($u) => $u->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('id')->paginate(30);

        return $this->page($page, fn (PackageUsage $u) => [
            'id' => $u->id, 'reason' => $u->reason, 'reason_label' => Str::headline($u->reason), 'quantity' => $u->quantity, 'value' => (float) $u->value,
            'at' => $u->created_at->toIso8601String(), 'appointment_date' => $u->appointment?->date?->toDateString(),
            'patient' => $u->patientPackage->patient->only(['id', 'name', 'patient_code']), 'package' => $u->patientPackage->package?->name,
            'left' => $u->patientPackage->total_sessions - $u->patientPackage->used_sessions, 'note' => $u->note,
        ]);
    }

    private function paymentScope(User $user): Builder
    {
        $branches = $user->accessibleBranchIds();
        $ownOnly = ! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::BranchAdmin->value, Role::Accountant->value]);

        return Payment::query()
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($ownOnly, fn ($q) => $q->where('received_by', $user->id));
    }

    private function invoices(User $user): Builder
    {
        $branches = $user->accessibleBranchIds();

        return Invoice::query()->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches));
    }

    private function page(LengthAwarePaginator $page, callable $map, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ...$extra,
        ]);
    }
}
