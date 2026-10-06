<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments (Plan §১৯, Accounts §৩). One payment can pay several invoices; whatever is not allocated
 * is held as the child's advance and applied automatically to the next invoice.
 *   Received:  Dr Cash/bKash/Nagad/Bank  /  Cr Receivable (allocated) + Cr Patient Advances (rest)
 *   Advance applied:  Dr Patient Advances / Cr Receivable
 *   Refund:  Dr Patient Advances / Cr Cash/...
 */
class PaymentService
{
    public function __construct(
        private IdGenerator $ids,
        private LedgerService $ledger,
        private AccountMap $accounts,
        private InvoiceService $invoices,
        private TimelineService $timeline,
    ) {}

    /**
     * @param  array{amount: float|string, method: string, branch_id: int, transaction_ref?: ?string, payer_name?: ?string, notes?: ?string, allocations?: list<array{invoice_id: int, amount: float|string}>}  $data
     */
    public function receive(Patient $patient, array $data, User $user): Payment
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
        }

        return DB::transaction(function () use ($patient, $data, $user, $amount) {
            $open = Invoice::where('patient_id', $patient->id)->open()->orderBy('issue_date')->orderBy('id')->lockForUpdate()->get();
            $plan = $this->allocationPlan($open, $amount, $data['allocations'] ?? null);

            $payment = Payment::create([
                'receipt_no' => $this->ids->next('receipt', 'RCP', 6),
                'patient_id' => $patient->id,
                'branch_id' => $data['branch_id'],
                'type' => 'payment',
                'amount' => $amount,
                'method' => $data['method'],
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
                'received_by' => $user->id,
                'status' => 'completed',
            ]);

            $allocated = 0;
            foreach ($plan as $invoiceId => $part) {
                $payment->allocations()->create(['invoice_id' => $invoiceId, 'amount' => $part]);
                $allocated += $part;
            }
            $advance = round($amount - $allocated, 2);

            $this->ledger->post('payment.received', today(), $payment->branch_id,
                "Receipt {$payment->receipt_no} — {$patient->name} ({$payment->method})", [
                    ['account' => $this->accounts->forMethod($payment->method, $payment->branch_id), 'debit' => $amount, 'memo' => $payment->transaction_ref],
                    ['account' => $this->accounts->system('receivable'), 'credit' => $allocated, 'party' => $patient],
                    ['account' => $this->accounts->system('patient_advances'), 'credit' => $advance, 'party' => $patient],
                ], $payment, 'receipt');

            $open->whereIn('id', array_keys($plan))->each(fn ($inv) => $this->invoices->refreshTotals($inv));
            $this->timeline->record($patient, 'payment.received', 'Payment received — ৳'.number_format($amount).' ('.strtoupper($payment->method).')', $payment,
                branchId: $payment->branch_id, visibility: 'parent');
            app(NotificationService::class)->parentsTemplate($patient, 'payment.received',
                ['amount' => NotificationService::bnTaka($amount), 'receipt_no' => $payment->receipt_no], '/portal/billing');

            return $payment->load('allocations.invoice');
        });
    }

    /** Applies the child's advance to open invoices (oldest first, or one invoice). */
    public function applyAdvance(Patient $patient, User $user, ?Invoice $only = null): float
    {
        return DB::transaction(function () use ($patient, $only) {
            $applied = 0;
            $invoices = $only ? collect([$only->fresh()]) : Invoice::where('patient_id', $patient->id)->open()->orderBy('issue_date')->orderBy('id')->get();

            foreach ($this->paymentsWithAdvance($patient) as [$payment, $available]) {
                foreach ($invoices as $invoice) {
                    $due = round((float) $invoice->due_total, 2);
                    if ($due <= 0 || $available <= 0 || ! in_array($invoice->status, Invoice::OPEN, true)) {
                        continue;
                    }
                    $part = min($due, $available);
                    $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $part, 'from_advance' => true]);
                    $this->ledger->post('advance.applied', today(), $invoice->branch_id,
                        "Advance from {$payment->receipt_no} applied to {$invoice->invoice_no}", [
                            ['account' => $this->accounts->system('patient_advances'), 'debit' => $part, 'party' => $patient],
                            ['account' => $this->accounts->system('receivable'), 'credit' => $part, 'party' => $patient],
                        ], $payment);
                    $this->invoices->refreshTotals($invoice);
                    $available = round($available - $part, 2);
                    $applied += $part;
                }
            }

            return round($applied, 2);
        });
    }

    /** Pays back unused advance. */
    public function refund(Patient $patient, array $data, User $user): Payment
    {
        $amount = round((float) $data['amount'], 2);
        $available = $this->advanceBalance($patient);
        if ($amount <= 0 || $amount > $available) {
            throw ValidationException::withMessages(['amount' => 'A refund can be at most the advance balance (৳'.number_format($available, 2).').']);
        }

        return DB::transaction(function () use ($patient, $data, $user, $amount) {
            $refund = Payment::create([
                'receipt_no' => $this->ids->next('refund', 'RFD', 6),
                'patient_id' => $patient->id, 'branch_id' => $data['branch_id'], 'type' => 'refund', 'amount' => $amount,
                'method' => $data['method'], 'transaction_ref' => $data['transaction_ref'] ?? null, 'notes' => $data['reason'],
                'paid_at' => now(), 'received_by' => $user->id, 'status' => 'completed',
            ]);
            $this->ledger->post('refund.paid', today(), $refund->branch_id, "Refund {$refund->receipt_no} — {$patient->name}: {$data['reason']}", [
                ['account' => $this->accounts->system('patient_advances'), 'debit' => $amount, 'party' => $patient],
                ['account' => $this->accounts->forMethod($refund->method, $refund->branch_id), 'credit' => $amount],
            ], $refund, 'payment');

            return $refund;
        });
    }

    /** Voids a payment/refund (wrong amount, bounced transfer): reverses every posting and re-opens the invoices. */
    public function void(Payment $payment, string $reason, User $user): Payment
    {
        if ($payment->status === 'void') {
            throw ValidationException::withMessages(['payment' => 'This payment is already void.']);
        }
        // Accounts §৮: once the cash for that day is closed, only accounts staff may change it.
        if ($payment->received_by && CashClosingService::isClosed($payment->received_by, $payment->paid_at) && ! $user->can(Permission::ACCOUNTS_COA_MANAGE)) {
            throw ValidationException::withMessages(['payment' => 'The cash for this day is already closed. Ask the accountant.']);
        }
        if ($payment->type === 'payment') {
            $heldAsAdvance = round((float) $payment->amount - (float) $payment->allocations()->sum('amount'), 2);
            if ($heldAsAdvance > $this->advanceBalance($payment->patient)) {
                throw ValidationException::withMessages(['payment' => 'Part of this payment was already refunded. Void the refund first.']);
            }
        }

        return DB::transaction(function () use ($payment, $reason, $user) {
            $this->ledger->reverseAllFor($payment, $reason);
            $invoiceIds = $payment->allocations()->pluck('invoice_id');
            $payment->allocations()->delete();
            Invoice::whereIn('id', $invoiceIds)->get()->each(fn ($inv) => $this->invoices->refreshTotals($inv));
            $payment->update(['status' => 'void', 'void_reason' => $reason, 'voided_at' => now(), 'voided_by' => $user->id]);
            AuditLogger::log('voided', $payment, new: ['reason' => $reason]);

            return $payment;
        });
    }

    public function advanceBalance(Patient $patient): float
    {
        $received = (float) Payment::where('patient_id', $patient->id)->where('type', 'payment')->where('status', 'completed')->sum('amount');
        $allocated = (float) PaymentAllocation::whereHas('payment', fn ($q) => $q->where('patient_id', $patient->id)->where('type', 'payment')->where('status', 'completed'))->sum('amount');
        $refunded = (float) Payment::where('patient_id', $patient->id)->where('type', 'refund')->where('status', 'completed')->sum('amount');

        return max(0, round($received - $allocated - $refunded, 2));
    }

    /** Payments that still hold unallocated money, oldest first; refunds are taken from the oldest advance. */
    private function paymentsWithAdvance(Patient $patient): array
    {
        $refunded = (float) Payment::where('patient_id', $patient->id)->where('type', 'refund')->where('status', 'completed')->sum('amount');
        $result = [];
        $payments = Payment::where('patient_id', $patient->id)->where('type', 'payment')->where('status', 'completed')
            ->withSum('allocations', 'amount')->orderBy('paid_at')->orderBy('id')->get();

        foreach ($payments as $payment) {
            $free = round((float) $payment->amount - (float) $payment->allocations_sum_amount, 2);
            $fromRefund = min($free, $refunded);
            $refunded -= $fromRefund;
            $free = round($free - $fromRefund, 2);
            if ($free > 0) {
                $result[] = [$payment, $free];
            }
        }

        return $result;
    }

    /** @return array<int, float> invoice id => amount */
    private function allocationPlan($open, float $amount, ?array $requested): array
    {
        $plan = [];
        if ($requested !== null) {
            foreach ($requested as $i => $row) {
                $invoice = $open->firstWhere('id', (int) $row['invoice_id'])
                    ?? throw ValidationException::withMessages(["allocations.$i.invoice_id" => 'This invoice is not open for this child.']);
                $part = round((float) $row['amount'], 2);
                if ($part <= 0) {
                    continue;
                }
                if ($part > (float) $invoice->due_total) {
                    throw ValidationException::withMessages(["allocations.$i.amount" => "More than the due on {$invoice->invoice_no}."]);
                }
                $plan[$invoice->id] = ($plan[$invoice->id] ?? 0) + $part;
            }
            if (array_sum($plan) - $amount > 0.001) {
                throw ValidationException::withMessages(['allocations' => 'Allocated more than the amount received.']);
            }

            return $plan;
        }

        // Default: oldest invoice first.
        $left = $amount;
        foreach ($open as $invoice) {
            if ($left <= 0) {
                break;
            }
            $part = min($left, round((float) $invoice->due_total, 2));
            if ($part > 0) {
                $plan[$invoice->id] = $part;
                $left = round($left - $part, 2);
            }
        }

        return $plan;
    }
}
