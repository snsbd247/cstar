<?php

namespace App\Services\OnlinePayment;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\OnlinePayment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Parent-portal online payments (Sprint 19). Flow: start() → parent pays on the gateway → the gateway calls
 * back → confirm*() asks the gateway server-to-server → markPaid() books a normal receipt (oldest invoice
 * first, rest as advance), exactly as if the front desk had taken the money. Every step is idempotent.
 */
class OnlinePaymentService
{
    public function __construct(private OnlinePaymentSettings $settings, private PaymentService $payments) {}

    /** What this child owes right now (only issued invoices). */
    public function due(Patient $patient): float
    {
        return round((float) Invoice::where('patient_id', $patient->id)->open()->sum('due_total'), 2);
    }

    /** @return array{payment: OnlinePayment, redirect_url: string} */
    public function start(Patient $patient, User $parent, string $gateway, float $amount, ?Invoice $invoice = null): array
    {
        if (! in_array($gateway, $this->settings->available(), true)) {
            throw ValidationException::withMessages(['gateway' => 'এই মাধ্যমে এখন অনলাইনে পরিশোধ করা যাচ্ছে না।']);
        }
        $amount = round($amount, 2);
        $limit = $invoice ? (float) $invoice->due_total : $this->due($patient);
        if ($amount < (float) $this->settings->get('min_amount') || $amount > $limit + 0.001) {
            throw ValidationException::withMessages(['amount' => 'টাকার পরিমাণ ৳'.(int) $this->settings->get('min_amount').' থেকে বকেয়া ৳'.number_format($limit).'-এর মধ্যে হতে হবে।']);
        }

        $payment = OnlinePayment::create([
            'tran_id' => 'OP'.now()->format('ymdHis').Str::upper(Str::random(6)), 'gateway' => $gateway,
            'patient_id' => $patient->id, 'branch_id' => $invoice?->branch_id ?? $patient->home_branch_id, 'invoice_id' => $invoice?->id,
            'user_id' => $parent->id, 'amount' => $amount, 'status' => 'initiated',
        ]);

        try {
            $redirect = match ($gateway) {
                'test' => route('pay.test', $payment->tran_id),
                'sslcommerz' => $this->sslcommerz()->start($payment, $this->customer($parent), [
                    'success' => route('pay.sslcommerz', 'success'), 'fail' => route('pay.sslcommerz', 'fail'),
                    'cancel' => route('pay.sslcommerz', 'cancel'), 'ipn' => route('pay.sslcommerz.ipn'),
                ]),
                'bkash' => $this->startBkash($payment, $parent),
            };
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
            throw ValidationException::withMessages(['gateway' => 'পেমেন্ট শুরু করা যায়নি, একটু পরে আবার চেষ্টা করুন।']);
        }

        return ['payment' => $payment, 'redirect_url' => $redirect];
    }

    private function startBkash(OnlinePayment $payment, User $parent): string
    {
        $created = $this->bkash()->create($payment, $parent->phone ?: $payment->tran_id, route('pay.bkash'));
        $payment->update(['gateway_ref' => $created['paymentID']]);

        return $created['bkashURL'];
    }

    /** SSLCommerz success / IPN: confirmed only through the validation API. */
    public function confirmSslCommerz(string $tranId, ?string $valId, string $reported): OnlinePayment
    {
        $payment = OnlinePayment::where('tran_id', $tranId)->where('gateway', 'sslcommerz')->firstOrFail();
        if ($payment->status === 'paid') {
            return $payment;
        }
        if (! $valId || ! in_array(strtoupper($reported), ['VALID', 'VALIDATED', 'SUCCESS'], true)) {
            return $this->close($payment, strtoupper($reported) === 'CANCELLED' ? 'cancelled' : 'failed', "SSLCommerz reported {$reported}");
        }
        $payment->update(['gateway_ref' => $valId]); // kept so staff can "Check again" later
        try {
            $v = $this->sslcommerz()->validate($valId);
        } catch (RuntimeException $e) {
            return $this->close($payment, 'review', $e->getMessage());
        }
        $raw = collect($v)->only(['status', 'tran_id', 'amount', 'currency', 'bank_tran_id', 'card_type', 'card_brand', 'risk_level', 'risk_title', 'tran_date'])->all();
        if (! in_array($v['status'] ?? '', ['VALID', 'VALIDATED'], true) || ($v['tran_id'] ?? '') !== $payment->tran_id) {
            return $this->close($payment, 'failed', 'SSLCommerz validation: '.($v['status'] ?? 'unknown'), $raw);
        }
        if (abs((float) ($v['amount'] ?? 0) - (float) $payment->amount) > 0.009 || ($v['currency'] ?? 'BDT') !== 'BDT') {
            return $this->close($payment, 'review', "Amount mismatch: gateway ৳{$v['amount']} {$v['currency']}, expected ৳{$payment->amount}", $raw);
        }
        if ((string) ($v['risk_level'] ?? '0') === '1') {
            return $this->close($payment, 'review', 'SSLCommerz marked the payment risky: '.($v['risk_title'] ?? ''), $raw);
        }

        return $this->markPaid($payment, Payment::ONLINE, $valId, $v['bank_tran_id'] ?? null, $v['card_type'] ?? null, $raw);
    }

    /** bKash callback: execute; if execute is unclear, ask for the status. */
    public function confirmBkash(string $paymentId, string $status): OnlinePayment
    {
        $payment = OnlinePayment::where('gateway', 'bkash')->where('gateway_ref', $paymentId)->firstOrFail();
        if ($payment->status === 'paid') {
            return $payment;
        }
        if ($status !== 'success') {
            return $this->close($payment, $status === 'cancel' ? 'cancelled' : 'failed', "bKash reported {$status}");
        }
        try {
            $result = $this->bkash()->execute($paymentId);
            if (($result['transactionStatus'] ?? null) !== 'Completed') {
                $result = $this->bkash()->query($paymentId);
            }
        } catch (RuntimeException $e) {
            return $this->close($payment, 'review', $e->getMessage());
        }
        $raw = collect($result)->only(['transactionStatus', 'trxID', 'amount', 'currency', 'customerMsisdn', 'statusMessage', 'paymentExecuteTime'])->all();
        if (($result['transactionStatus'] ?? null) !== 'Completed') {
            return $this->close($payment, 'failed', 'bKash: '.($result['statusMessage'] ?? $result['transactionStatus'] ?? 'not completed'), $raw);
        }
        if (abs((float) ($result['amount'] ?? 0) - (float) $payment->amount) > 0.009) {
            return $this->close($payment, 'review', "Amount mismatch: bKash ৳{$result['amount']}, expected ৳{$payment->amount}", $raw);
        }

        return $this->markPaid($payment, 'bkash', $paymentId, $result['trxID'] ?? null, 'bKash', $raw);
    }

    /** The pretend gateway for training copies. */
    public function confirmTest(OnlinePayment $payment, bool $pay): OnlinePayment
    {
        abort_if(app()->isProduction() || $payment->gateway !== 'test', 404);

        return $pay ? $this->markPaid($payment, Payment::ONLINE, 'TEST', 'TEST-'.Str::upper(Str::random(8)), 'Test card', ['test' => true])
            : $this->close($payment, 'cancelled', 'Cancelled on the test page');
    }

    private function markPaid(OnlinePayment $payment, string $method, ?string $ref, ?string $trx, ?string $instrument, array $raw): OnlinePayment
    {
        return DB::transaction(function () use ($payment, $method, $ref, $trx, $instrument, $raw) {
            $payment = OnlinePayment::whereKey($payment->id)->lockForUpdate()->first();
            if ($payment->status === 'paid') {
                return $payment; // the IPN and the browser can arrive together
            }
            $invoice = $payment->invoice_id ? Invoice::whereKey($payment->invoice_id)->open()->first() : null;
            $receipt = $this->payments->receive($payment->patient, [
                'amount' => (float) $payment->amount, 'method' => $method, 'branch_id' => $payment->branch_id,
                'transaction_ref' => $trx ?? $ref, 'payer_name' => $payment->user?->name,
                'notes' => 'Paid online — '.match ($payment->gateway) { 'bkash' => 'bKash', 'sslcommerz' => 'SSLCommerz', default => 'test gateway' }." ({$payment->tran_id})",
                'allocations' => $invoice && (float) $invoice->due_total >= (float) $payment->amount ? [['invoice_id' => $invoice->id, 'amount' => (float) $payment->amount]] : null,
            ], $payment->user ?? User::role('super_admin')->firstOrFail());
            $payment->update(['status' => 'paid', 'gateway_ref' => $ref ?? $payment->gateway_ref, 'gateway_trx' => $trx, 'instrument' => $instrument,
                'payment_id' => $receipt->id, 'raw' => $raw, 'paid_at' => now(), 'error' => null]);
            AuditLogger::log('online_payment.paid', $payment, null, ['tran_id' => $payment->tran_id, 'receipt' => $receipt->receipt_no, 'amount' => (float) $payment->amount]);

            return $payment;
        });
    }

    /** Failed / cancelled — or "review": money may have moved, so staff must check with the gateway. */
    private function close(OnlinePayment $payment, string $status, string $error, array $raw = []): OnlinePayment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }
        $payment->update(['status' => $status, 'error' => mb_substr($error, 0, 500), 'raw' => $raw ?: $payment->raw]);
        if ($status === 'review') {
            app(NotificationService::class)->toStaff(Permission::PAYMENTS_VIEW, $payment->branch_id, 'online_payment.review',
                "Online payment {$payment->tran_id} needs checking", "{$payment->patient->name} — ৳".number_format((float) $payment->amount).": {$error}", '/app/billing/online');
        }

        return $payment;
    }

    private function customer(User $parent): array
    {
        $center = app(SystemSettings::class)->group('center');

        return [
            'name' => $parent->name, 'phone' => $parent->phone ?: '01700000000',
            'email' => $parent->email ?: 'noreply@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'cstar.local'),
            'address' => $center['address'] !== '' ? Str::limit($center['address'], 50, '') : 'Dhaka',
        ];
    }

    public function sslcommerz(): SslCommerzGateway
    {
        return new SslCommerzGateway($this->settings->get('sslcommerz_store_id'), $this->settings->get('sslcommerz_store_password'), $this->settings->flag('sslcommerz_sandbox'));
    }

    public function bkash(): BkashGateway
    {
        return new BkashGateway($this->settings->get('bkash_app_key'), $this->settings->get('bkash_app_secret'),
            $this->settings->get('bkash_username'), $this->settings->get('bkash_password'), $this->settings->flag('bkash_sandbox'));
    }
}
