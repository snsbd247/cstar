<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Payments, refunds, the child's billing account, due list and daily collection. */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** GET /payments?patient_id=&date=&method=&received_by= */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::PAYMENTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $payments = Payment::with(['patient', 'branch', 'receiver', 'allocations.invoice'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('paid_at', $request->date('date')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            ->when($request->filled('received_by'), fn ($q) => $q->where('received_by', $request->integer('received_by')))
            ->latest('paid_at')->latest('id')
            ->paginate($request->integer('per_page', 25));

        return PaymentResource::collection($payments);
    }

    /** POST /patients/{id}/payments — allocations optional (default: oldest invoice first, rest = advance). */
    public function store(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_CREATE);
        $this->authorizePatient($request, $patient);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_ref' => ['nullable', 'required_unless:method,cash', 'string', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ], ['transaction_ref.required_unless' => 'Enter the transaction ID for bKash, Nagad, bank or card payments.']);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403, 'You can only take payments in your own branch.');

        $payment = $this->payments->receive($patient, $data, $request->user());

        return (new PaymentResource($payment->load(['patient', 'branch', 'receiver', 'allocations.invoice'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $this->authorizeView($request, $payment);

        return new PaymentResource($payment->load(['patient', 'branch', 'receiver', 'allocations.invoice']));
    }

    public function refund(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_VOID);
        $this->authorizePatient($request, $patient);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        return (new PaymentResource($this->payments->refund($patient, $data, $request->user())->load(['patient', 'branch', 'receiver'])))->response()->setStatusCode(201);
    }

    public function void(Request $request, Payment $payment): PaymentResource
    {
        Gate::authorize(Permission::INVOICES_VOID);
        $this->authorizeView($request, $payment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return new PaymentResource($this->payments->void($payment->load('patient'), $data['reason'], $request->user())->load(['patient', 'branch', 'receiver', 'allocations.invoice']));
    }

    public function receipt(Request $request, Payment $payment, PdfService $pdf): Response
    {
        $this->authorizeView($request, $payment);
        $payment->load(['patient', 'branch', 'receiver', 'allocations.invoice']);
        $advance = $this->payments->advanceBalance($payment->patient);
        $due = (float) Invoice::where('patient_id', $payment->patient_id)->open()->sum('due_total');

        return $pdf->response('pdf.receipt', compact('payment', 'advance', 'due'), $payment->type === 'refund' ? 'Refund Receipt' : 'Money Receipt', "{$payment->receipt_no}.pdf");
    }

    /** GET /patients/{id}/billing — the child's account at a glance. */
    public function account(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_VIEW);
        $this->authorizePatient($request, $patient);

        $invoices = Invoice::with(['branch', 'items', 'allocations.payment'])->where('patient_id', $patient->id)
            ->latest('issue_date')->latest('id')->limit(50)->get();
        $packages = PatientPackage::with(['package', 'service'])->where('patient_id', $patient->id)->latest('id')->get();
        $payments = Payment::with(['branch', 'receiver', 'allocations.invoice'])->where('patient_id', $patient->id)->latest('paid_at')->limit(30)->get();

        return response()->json(['data' => [
            'due' => round((float) $invoices->whereIn('status', Invoice::OPEN)->sum('due_total'), 2),
            'advance' => $this->payments->advanceBalance($patient),
            'invoices' => InvoiceResource::collection($invoices),
            'payments' => PaymentResource::collection($payments),
            'packages' => $packages->map(fn (PatientPackage $p) => [
                'id' => $p->id, 'name' => $p->package->name, 'service' => $p->service->name, 'status' => $p->status,
                'total_sessions' => $p->total_sessions, 'used_sessions' => $p->used_sessions, 'remaining' => $p->remaining(),
                'start_date' => $p->start_date->toDateString(), 'expiry_date' => $p->expiry_date->toDateString(), 'price' => (float) $p->price,
            ]),
        ]]);
    }

    /** GET /billing/dues — children with money owed, biggest first. */
    public function dues(Request $request): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $rows = Invoice::open()
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->select('patient_id', DB::raw('SUM(due_total) as due'), DB::raw('COUNT(*) as invoices'), DB::raw('MIN(due_date) as oldest_due_date'))
            ->groupBy('patient_id')->orderByDesc('due')->limit(200)->get();
        $patients = Patient::whereIn('id', $rows->pluck('patient_id'))->get(['id', 'name', 'patient_code', 'phone'])->keyBy('id');

        return response()->json(['data' => $rows->map(fn ($r) => [
            'patient' => $patients[$r->patient_id] ?? null,
            'due' => round((float) $r->due, 2),
            'invoices' => (int) $r->invoices,
            'oldest_due_date' => $r->oldest_due_date,
            'overdue' => $r->oldest_due_date && $r->oldest_due_date < today()->toDateString(),
        ])->values(), 'total' => round((float) $rows->sum('due'), 2)]);
    }

    /**
     * GET /billing/collection?date= — daily collection by method (Plan §১৯ "দিন শেষে").
     * A receptionist sees their own; admins and accountants see the whole branch, per person.
     */
    public function collection(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_VIEW);
        $user = $request->user();
        $date = $request->date('date') ?? today();
        $ownOnly = ! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::BranchAdmin->value, Role::Accountant->value]);
        $branches = $user->accessibleBranchIds();

        $payments = Payment::with('receiver')->where('status', 'completed')->whereDate('paid_at', $date)
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($ownOnly, fn ($q) => $q->where('received_by', $user->id))
            ->get();
        $sign = fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'own_only' => $ownOnly,
            'total' => round($payments->sum($sign), 2),
            'count' => $payments->where('type', 'payment')->count(),
            'by_method' => collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => round($payments->where('method', $m)->sum($sign), 2)]),
            'by_user' => $payments->groupBy('received_by')->map(fn ($list) => [
                'user' => $list->first()->receiver?->only(['id', 'name']),
                'total' => round($list->sum($sign), 2),
                'by_method' => collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => round($list->where('method', $m)->sum($sign), 2)]),
            ])->values(),
        ]]);
    }

    private function authorizePatient(Request $request, Patient $patient): void
    {
        abort_unless(Patient::visibleTo($request->user())->whereKey($patient->id)->exists(), 403);
    }

    private function authorizeView(Request $request, Payment $payment): void
    {
        abort_unless($request->user()->can(Permission::PAYMENTS_VIEW) && $request->user()->canAccessBranch($payment->branch_id), 403);
    }
}
