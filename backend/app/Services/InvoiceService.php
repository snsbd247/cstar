<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoices (Plan §১৯): draft → issued → partially_paid/paid, or void.
 * Issuing posts to the ledger (Accounts §৩): Dr Receivable + Dr Discount / Cr Income (package lines → Cr Unearned, net).
 */
class InvoiceService
{
    public function __construct(
        private IdGenerator $ids,
        private LedgerService $ledger,
        private AccountMap $accounts,
        private BillingSettings $settings,
        private TimelineService $timeline,
    ) {}

    public function createDraft(Patient $patient, array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($patient, $data, $user) {
            $invoice = Invoice::create([
                ...Arr::only($data, ['branch_id', 'guardian_id', 'due_date', 'discount_reason', 'notes']),
                'patient_id' => $patient->id,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            $this->replaceItems($invoice, $data['items'] ?? []);

            return $invoice;
        });
    }

    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update(Arr::only($data, ['branch_id', 'guardian_id', 'due_date', 'discount_reason', 'notes']));
            if (array_key_exists('items', $data)) {
                $this->replaceItems($invoice, $data['items']);
            }

            return $invoice;
        });
    }

    public function deleteDraft(Invoice $invoice): void
    {
        $this->assertDraft($invoice);
        $invoice->delete();
    }

    /** Draft → issued: number, ledger posting, package activation, advance applied. */
    public function issue(Invoice $invoice, User $user, ?Carbon $issueDate = null): Invoice
    {
        $this->assertDraft($invoice);
        $invoice->loadMissing(['items.service', 'items.package', 'patient']);
        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }
        $this->assertDiscountAllowed($invoice, $user);

        return DB::transaction(function () use ($invoice, $user, $issueDate) {
            $date = $issueDate ?? today();
            $invoice->update([
                'invoice_no' => $this->ids->next('invoice', 'INV', 6, $date->year),
                'issue_date' => $date,
                'due_date' => $invoice->due_date ?? $date->copy()->addDays((int) $this->settings->get('invoice_due_days')),
                'status' => 'issued',
                'issued_by' => $user->id,
            ]);

            $this->postIssue($invoice);
            $this->activatePackages($invoice);
            $this->refreshTotals($invoice);

            $this->timeline->record($invoice->patient, 'invoice.issued', "Invoice {$invoice->invoice_no} — ৳".number_format((float) $invoice->total), $invoice,
                branchId: $invoice->branch_id, visibility: 'parent');

            // Money already held as advance pays the new invoice straight away.
            app(PaymentService::class)->applyAdvance($invoice->patient, $user, $invoice);
            $invoice->refresh();
            if ((float) $invoice->due_total > 0) {
                app(NotificationService::class)->parentsTemplate($invoice->patient, 'invoice.issued',
                    ['invoice_no' => $invoice->invoice_no, 'amount' => NotificationService::bnTaka((float) $invoice->due_total)], '/portal/billing');
            }

            return $invoice->refresh();
        });
    }

    /** Create and issue in one step (automatic charges: sessions, monthly fees, packages). */
    public function createAndIssue(Patient $patient, int $branchId, array $items, User $user, array $extra = []): Invoice
    {
        return DB::transaction(function () use ($patient, $branchId, $items, $user, $extra) {
            $invoice = $this->createDraft($patient, ['branch_id' => $branchId, 'items' => $items, ...$extra], $user);

            return $this->issue($invoice, $user);
        });
    }

    /** Issued invoices are never deleted (Plan §১৯) — void reverses the postings and returns any payment as advance. */
    public function void(Invoice $invoice, string $reason, User $user): Invoice
    {
        if ($invoice->status === 'void') {
            throw ValidationException::withMessages(['invoice' => 'This invoice is already void.']);
        }
        if ($invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Delete the draft instead of voiding it.']);
        }

        $packages = PatientPackage::whereIn('invoice_item_id', $invoice->items()->pluck('id'))->get();
        if ($packages->contains(fn ($p) => $p->used_sessions > 0)) {
            throw ValidationException::withMessages(['invoice' => 'A package on this invoice has already been used. Adjust the package instead of voiding.']);
        }

        return DB::transaction(function () use ($invoice, $reason, $user, $packages) {
            $this->ledger->reverseAllFor($invoice, $reason);

            // Paid money goes back to the child's advance.
            $invoice->loadMissing('patient');
            foreach ($invoice->allocations()->with('payment')->get() as $allocation) {
                $this->ledger->post('allocation.released', today(), $invoice->branch_id, "Payment released from void invoice {$invoice->invoice_no}", [
                    ['account' => $this->accounts->system('receivable'), 'debit' => $allocation->amount, 'party' => $invoice->patient],
                    ['account' => $this->accounts->system('patient_advances'), 'credit' => $allocation->amount, 'party' => $invoice->patient],
                ], $allocation->payment);
                $allocation->delete();
            }

            $packages->each->update(['status' => 'cancelled']);
            $invoice->update(['status' => 'void', 'void_reason' => $reason, 'voided_at' => now(), 'voided_by' => $user->id, 'paid_total' => 0, 'due_total' => 0]);
            AuditLogger::log('voided', $invoice, new: ['reason' => $reason]);

            return $invoice;
        });
    }

    /** Recomputes paid/due/status from allocations. */
    public function refreshTotals(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, ['draft', 'void'], true)) {
            return $invoice;
        }
        $paid = round((float) $invoice->allocations()->sum('amount'), 2);
        $due = round((float) $invoice->total - $paid, 2);
        $invoice->update([
            'paid_total' => $paid,
            'due_total' => max(0, $due),
            'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'issued'),
        ]);

        return $invoice;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function replaceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        $subtotal = 0;
        $discount = 0;

        foreach ($items as $i => $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unit = round((float) $item['unit_price'], 2);
            $lineDiscount = round((float) ($item['discount'] ?? 0), 2);
            $gross = round($qty * $unit, 2);
            if ($lineDiscount > $gross) {
                throw ValidationException::withMessages(["items.$i.discount" => 'Discount cannot be more than the line amount.']);
            }

            $package = isset($item['package_id']) ? Package::find($item['package_id']) : null;
            $service = isset($item['service_id']) ? Service::find($item['service_id']) : $package?->service;

            $invoice->items()->create([
                ...Arr::only($item, ['enrollment_id', 'appointment_id', 'therapy_session_id', 'billing_period']),
                'item_type' => $item['item_type'],
                'service_id' => $service?->id,
                'package_id' => $package?->id,
                'description' => $item['description'] ?? $package?->name ?? $service?->name ?? ucfirst(str_replace('_', ' ', $item['item_type'])),
                'quantity' => $qty,
                'unit_price' => $unit,
                'discount' => $lineDiscount,
                'line_total' => $gross - $lineDiscount,
            ]);
            $subtotal += $gross;
            $discount += $lineDiscount;
        }

        $total = round($subtotal - $discount, 2);
        $invoice->update(['subtotal' => $subtotal, 'discount_total' => $discount, 'total' => $total, 'due_total' => $total, 'paid_total' => 0]);
    }

    /** Role-based discount limit; a reason is always required (Plan §১৯). */
    private function assertDiscountAllowed(Invoice $invoice, User $user): void
    {
        $discount = (float) $invoice->discount_total;
        if ($discount <= 0) {
            return;
        }
        if (blank($invoice->discount_reason)) {
            throw ValidationException::withMessages(['discount_reason' => 'Write the reason for the discount.']);
        }
        if (! $user->can(Permission::DISCOUNTS_APPLY)) {
            throw ValidationException::withMessages(['discount' => 'You are not allowed to give discounts.']);
        }
        $unlimited = $user->isSuperAdmin() || $user->hasAnyRole([Role::BranchAdmin->value, Role::Accountant->value]);
        $limit = (float) $this->settings->get('receptionist_discount_limit_percent');
        if (! $unlimited && $discount > round((float) $invoice->subtotal * $limit / 100, 2)) {
            throw ValidationException::withMessages(['discount' => "Discounts above {$limit}% need a branch admin or accountant."]);
        }
    }

    private function postIssue(Invoice $invoice): void
    {
        $receivable = $this->accounts->system('receivable');
        $lines = [['account' => $receivable, 'debit' => $invoice->total, 'party' => $invoice->patient]];
        $discountAllowed = 0;

        foreach ($invoice->items as $item) {
            if ($item->item_type === 'package') {
                // A package discount simply lowers the unearned amount; income is recognised session by session.
                $lines[] = ['account' => $this->accounts->system('unearned_package'), 'credit' => $item->line_total, 'service_id' => $item->service_id, 'party' => $invoice->patient, 'memo' => $item->description];

                continue;
            }
            $lines[] = ['account' => $this->accounts->incomeFor($item->item_type, $item->service), 'credit' => $item->gross(), 'service_id' => $item->service_id, 'memo' => $item->description];
            $discountAllowed += (float) $item->discount;
        }
        if ($discountAllowed > 0) {
            $lines[] = ['account' => $this->accounts->system('discount_allowed'), 'debit' => $discountAllowed, 'memo' => $invoice->discount_reason];
        }

        $this->ledger->post('invoice.issued', $invoice->issue_date, $invoice->branch_id,
            "Invoice {$invoice->invoice_no} — {$invoice->patient->name}", $lines, $invoice);
    }

    private function activatePackages(Invoice $invoice): void
    {
        foreach ($invoice->items->where('item_type', 'package') as $item) {
            $package = $item->package ?? throw ValidationException::withMessages(['items' => 'A package line needs a package.']);
            $pp = PatientPackage::create([
                'patient_id' => $invoice->patient_id,
                'package_id' => $package->id,
                'service_id' => $package->service_id,
                'enrollment_id' => $item->enrollment_id,
                'invoice_item_id' => $item->id,
                'branch_id' => $invoice->branch_id,
                'total_sessions' => $package->sessions_count * $item->quantity,
                'price' => $item->line_total,
                'start_date' => $invoice->issue_date,
                'expiry_date' => $invoice->issue_date->copy()->addDays($package->validity_days),
                'status' => 'active',
            ]);
            $item->update(['patient_package_id' => $pp->id]);
        }
    }

    private function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Only a draft invoice can be changed.']);
        }
    }

    /** Lines for a new invoice that lists a single charge. */
    public static function line(string $type, string $description, float $price, array $extra = []): array
    {
        return ['item_type' => $type, 'description' => $description, 'quantity' => 1, 'unit_price' => $price, ...$extra];
    }

    /** True when a non-void invoice already charges this enrollment for the month. */
    public static function alreadyBilled(int $enrollmentId, string $period): bool
    {
        return InvoiceItem::where('enrollment_id', $enrollmentId)->where('billing_period', $period)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->exists();
    }
}
