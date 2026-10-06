<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\ChargeService;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Invoices (Plan §১৯). Staff see invoices of their own branches. */
class InvoiceController extends Controller
{
    private const WITH = ['patient', 'branch', 'items', 'allocations.payment'];

    public function __construct(private InvoiceService $invoices) {}

    /** GET /invoices?status=&patient_id=&q=&from=&to=&overdue=1 */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(Permission::INVOICES_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $invoices = Invoice::with(['patient', 'branch'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->string('status')->toString() === 'open', fn ($q) => $q->open(), fn ($q) => $q->when($request->filled('status'), fn ($s) => $s->where('status', $request->string('status'))))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->whereDate('due_date', '<', today()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('invoice_no', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%')->orWhere('patient_code', 'like', '%'.$request->string('q').'%'))))
            ->orderByRaw("status = 'draft' desc")->latest('issue_date')->latest('id')
            ->paginate($request->integer('per_page', 25));

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorizeView($request, $invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        $data = $this->validated($request, true);
        $patient = Patient::visibleTo($request->user())->findOrFail($data['patient_id']);
        $this->authorizeBranch($request, (int) $data['branch_id']);

        $invoice = $this->invoices->createDraft($patient, $data, $request->user());
        if (! empty($data['issue'])) {
            $invoice = $this->invoices->issue($invoice, $request->user());
        }

        return (new InvoiceResource($invoice->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        $this->authorizeView($request, $invoice);
        $data = $this->validated($request, false);
        if (isset($data['branch_id'])) {
            $this->authorizeBranch($request, (int) $data['branch_id']);
        }

        $this->invoices->updateDraft($invoice, $data);
        if (! empty($data['issue'])) {
            $this->invoices->issue($invoice->refresh(), $request->user());
        }

        return new InvoiceResource($invoice->refresh()->load(self::WITH));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        $this->authorizeView($request, $invoice);
        $this->invoices->deleteDraft($invoice);

        return response()->json(['message' => 'Draft deleted.']);
    }

    public function issue(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        $this->authorizeView($request, $invoice);

        return new InvoiceResource($this->invoices->issue($invoice, $request->user())->load(self::WITH));
    }

    public function void(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize(Permission::INVOICES_VOID);
        $this->authorizeView($request, $invoice);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return new InvoiceResource($this->invoices->void($invoice, $data['reason'], $request->user())->load(self::WITH));
    }

    public function pdf(Request $request, Invoice $invoice, PdfService $pdf): Response
    {
        $this->authorizeView($request, $invoice);
        $invoice->load([...self::WITH, 'patient.guardians', 'items.service']);
        AuditLogger::log('exported', $invoice, new: ['format' => 'pdf']);

        return $pdf->response('pdf.invoice', ['invoice' => $invoice], $invoice->isDraft() ? 'Draft Invoice' : 'Invoice', ($invoice->invoice_no ?? 'draft-'.$invoice->id).'.pdf');
    }

    /** POST /billing/training-fees {month: YYYY-MM, branch_id?} — also runs automatically on the 1st. */
    public function generateTrainingFees(Request $request, ChargeService $charges): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id']]);
        if (isset($data['branch_id'])) {
            $this->authorizeBranch($request, (int) $data['branch_id']);
        }
        $branchId = $data['branch_id'] ?? ($request->user()->accessibleBranchIds() !== null && count($request->user()->accessibleBranchIds()) === 1 ? $request->user()->accessibleBranchIds()[0] : null);

        $created = $charges->generateTrainingFees(Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth(), $request->user(), $branchId);

        return response()->json(['created' => $created, 'message' => $created ? "{$created} training fee invoices created." : 'Every active student is already billed for this month.']);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'patient_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'branch_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:branches,id'],
            'guardian_id' => ['nullable', 'integer', 'exists:guardians,id'],
            'due_date' => ['nullable', 'date'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:30'],
            'items.*.item_type' => ['required', Rule::in(InvoiceItem::TYPES)],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.package_id' => ['nullable', 'required_if:items.*.item_type,package', 'integer', 'exists:packages,id'],
            'items.*.enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
            'items.*.billing_period' => ['nullable', 'date_format:Y-m'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'between:1,100'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'issue' => ['boolean'],
        ], ['items.required' => 'Add at least one item.']);
    }

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()->can(Permission::INVOICES_VIEW) && $request->user()->canAccessBranch($invoice->branch_id), 403);
    }

    private function authorizeBranch(Request $request, int $branchId): void
    {
        abort_unless($request->user()->canAccessBranch($branchId), 403, 'You can only bill in your own branch.');
    }
}
