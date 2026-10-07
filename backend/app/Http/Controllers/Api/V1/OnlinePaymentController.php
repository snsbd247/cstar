<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\OnlinePayment\OnlinePaymentService;
use App\Services\OnlinePayment\OnlinePaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Online payment (Sprint 19): portal endpoints for parents, the gateway return / IPN / callback routes
 * (public — they verify everything with the gateway), and the staff list and settings.
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private OnlinePaymentService $service, private OnlinePaymentSettings $settings) {}

    // ---- Parent portal -----------------------------------------------------------------------------

    private function myChild(Request $request, Patient $patient): void
    {
        $guardian = $request->user()->guardian;
        abort_unless($request->user()->can(Permission::PORTAL_ACCESS) && $guardian
            && $guardian->patients()->wherePivot('can_access_portal', true)->whereKey($patient->id)->exists(), 404);
    }

    /** GET /portal/children/{patient}/online-payment — can this child's bill be paid online, and how much is due. */
    public function options(Request $request, Patient $patient): JsonResponse
    {
        $this->myChild($request, $patient);

        return response()->json(['data' => [
            'gateways' => $this->settings->available(), 'due' => $this->service->due($patient), 'min_amount' => (float) $this->settings->get('min_amount'),
            'invoices' => Invoice::where('patient_id', $patient->id)->open()->orderBy('issue_date')->get(['id', 'invoice_no', 'due_total', 'issue_date'])
                ->map(fn ($i) => ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'due' => (float) $i->due_total, 'date' => $i->issue_date?->toDateString()]),
        ]]);
    }

    public function start(Request $request, Patient $patient): JsonResponse
    {
        $this->myChild($request, $patient);
        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:15'],
            'amount' => ['required', 'numeric', 'min:1', 'max:500000'],
            'invoice_id' => ['nullable', 'integer'],
        ]);
        $invoice = isset($data['invoice_id']) ? Invoice::where('patient_id', $patient->id)->open()->findOrFail($data['invoice_id']) : null;
        $result = $this->service->start($patient, $request->user(), $data['gateway'], (float) $data['amount'], $invoice);

        return response()->json(['data' => ['tran_id' => $result['payment']->tran_id, 'redirect_url' => $result['redirect_url']]], 201);
    }

    public function status(Request $request, string $tranId): JsonResponse
    {
        $payment = OnlinePayment::with('payment:id,receipt_no')->where('tran_id', $tranId)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['data' => [
            'tran_id' => $payment->tran_id, 'status' => $payment->status, 'amount' => (float) $payment->amount,
            'receipt_no' => $payment->payment?->receipt_no, 'payment_id' => $payment->payment_id, 'patient_id' => $payment->patient_id,
        ]]);
    }

    // ---- Gateway returns (public web routes, CSRF-exempt) -------------------------------------------

    /** SSLCommerz posts the browser back to success / fail / cancel. */
    public function sslcommerzReturn(Request $request, string $result): RedirectResponse
    {
        $tranId = (string) $request->input('tran_id');
        if (OnlinePayment::where('tran_id', $tranId)->exists()) {
            $this->service->confirmSslCommerz($tranId, $request->input('val_id'), $result === 'success' ? (string) $request->input('status', 'VALID') : strtoupper($result === 'cancel' ? 'CANCELLED' : 'FAILED'));
        }

        return redirect('/portal/billing?online='.urlencode($tranId));
    }

    /** SSLCommerz server-to-server notification — arrives even if the parent closes the browser. */
    public function sslcommerzIpn(Request $request): Response
    {
        $tranId = (string) $request->input('tran_id');
        if (OnlinePayment::where('tran_id', $tranId)->exists()) {
            $this->service->confirmSslCommerz($tranId, $request->input('val_id'), (string) $request->input('status'));
        }

        return response('OK');
    }

    public function bkashCallback(Request $request): RedirectResponse
    {
        $payment = OnlinePayment::where('gateway', 'bkash')->where('gateway_ref', (string) $request->query('paymentID'))->first();
        if ($payment) {
            $payment = $this->service->confirmBkash($payment->gateway_ref, (string) $request->query('status'));
        }

        return redirect('/portal/billing?online='.urlencode($payment?->tran_id ?? ''));
    }

    public function testPage(string $tranId): View
    {
        abort_if(app()->isProduction(), 404);
        $payment = OnlinePayment::with('patient:id,name')->where('tran_id', $tranId)->where('gateway', 'test')->where('status', 'initiated')->firstOrFail();

        return view('pay.test', ['payment' => $payment]);
    }

    public function testDecide(Request $request, string $tranId): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);
        $payment = OnlinePayment::where('tran_id', $tranId)->where('gateway', 'test')->firstOrFail();
        $this->service->confirmTest($payment, $request->input('decision') === 'pay');

        return redirect('/portal/billing?online='.urlencode($tranId));
    }

    // ---- Staff ------------------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $page = OnlinePayment::with(['patient:id,name,patient_code', 'payment:id,receipt_no', 'user:id,name'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')->paginate(30);
        $month = OnlinePayment::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches));

        return response()->json([
            'data' => collect($page->items())->map(fn (OnlinePayment $p) => [
                'id' => $p->id, 'tran_id' => $p->tran_id, 'gateway' => $p->gateway, 'amount' => (float) $p->amount, 'status' => $p->status,
                'patient' => $p->patient->only(['id', 'name', 'patient_code']), 'parent' => $p->user?->name, 'instrument' => $p->instrument,
                'gateway_trx' => $p->gateway_trx, 'receipt' => $p->payment?->only(['id', 'receipt_no']), 'error' => $p->error,
                'created_at' => $p->created_at->toIso8601String(), 'paid_at' => $p->paid_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'summary' => ['paid_count' => (clone $month)->count(), 'paid_total' => round((float) (clone $month)->sum('amount'), 2),
                'review' => OnlinePayment::where('status', 'review')->count()],
        ]);
    }

    /** Asks the gateway again — for a payment stuck in "review" or "initiated". */
    public function recheck(OnlinePayment $onlinePayment): JsonResponse
    {
        Gate::authorize(Permission::PAYMENTS_CREATE);
        abort_if($onlinePayment->status === 'paid', 422, 'Already paid.');
        $p = match ($onlinePayment->gateway) {
            'bkash' => $onlinePayment->gateway_ref ? $this->service->confirmBkash($onlinePayment->gateway_ref, 'success') : $onlinePayment,
            'sslcommerz' => $onlinePayment->gateway_ref ? $this->service->confirmSslCommerz($onlinePayment->tran_id, $onlinePayment->gateway_ref, 'VALID') : $onlinePayment,
            default => $onlinePayment,
        };

        return response()->json(['data' => ['status' => $p->status, 'error' => $p->error]]);
    }

    public function settings(): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);

        return response()->json(['data' => $this->settings->forScreen()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $data = $request->validate([
            'sslcommerz_enabled' => ['required', 'in:0,1'], 'sslcommerz_sandbox' => ['required', 'in:0,1'],
            'sslcommerz_store_id' => ['nullable', 'string', 'max:60'], 'sslcommerz_store_password' => ['nullable', 'string', 'max:100'],
            'bkash_enabled' => ['required', 'in:0,1'], 'bkash_sandbox' => ['required', 'in:0,1'],
            'bkash_app_key' => ['nullable', 'string', 'max:100'], 'bkash_app_secret' => ['nullable', 'string', 'max:200'],
            'bkash_username' => ['nullable', 'string', 'max:60'], 'bkash_password' => ['nullable', 'string', 'max:100'],
            'test_enabled' => ['required', 'in:0,1'], 'min_amount' => ['required', 'integer', 'between:10,100000'],
        ]);
        $missing = fn (string $key) => blank($data[$key] ?? null) && $this->settings->get($key) === '';
        if ($data['sslcommerz_enabled'] === '1' && ($missing('sslcommerz_store_id') || $missing('sslcommerz_store_password'))) {
            return response()->json(['message' => 'SSLCommerz store ID and password are needed.', 'errors' => ['sslcommerz_store_id' => ['Enter the store ID and password from SSLCommerz.']]], 422);
        }
        if ($data['bkash_enabled'] === '1' && collect(['bkash_app_key', 'bkash_app_secret', 'bkash_username', 'bkash_password'])->contains($missing)) {
            return response()->json(['message' => 'All four bKash credentials are needed.', 'errors' => ['bkash_app_key' => ['Enter app key, app secret, username and password from bKash.']]], 422);
        }
        if ($data['test_enabled'] === '1' && app()->isProduction()) {
            return response()->json(['message' => 'The test gateway cannot be used on the live site.', 'errors' => ['test_enabled' => ['Not on the live site.']]], 422);
        }
        $this->settings->update($data);
        AuditLogger::log('payment.gateway_settings', null, null, collect($data)->except(['sslcommerz_store_password', 'bkash_app_secret', 'bkash_password'])->all());

        return $this->settings();
    }
}
