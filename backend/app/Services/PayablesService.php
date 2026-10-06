<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendors and payables (Accounts §৬):
 *   bill:     Dr expense/asset lines / Cr Accounts Payable (vendor)
 *   payment:  Dr Accounts Payable / Cr cash/bank — applied to the oldest unpaid bills first
 *   opening:  money already owed at go-live → Dr Retained Earnings / Cr Accounts Payable (A9)
 */
class PayablesService
{
    public function __construct(private IdGenerator $ids, private LedgerService $ledger, private AccountMap $accounts) {}

    public function createVendor(array $data, User $user): Vendor
    {
        return DB::transaction(function () use ($data) {
            $vendor = Vendor::create($data);
            if ((float) ($data['opening_balance'] ?? 0) > 0) {
                $this->ledger->post('vendor.opening', today(), null, "Opening balance owed to {$vendor->name}", [
                    ['account' => $this->accounts->system('retained_earnings'), 'debit' => $data['opening_balance']],
                    ['account' => $this->accounts->system('payable'), 'credit' => $data['opening_balance'], 'party' => $vendor],
                ], $vendor, 'journal');
            }

            return $vendor;
        });
    }

    /** @param  list<array{account_id: int, description: string, amount: float|string}>  $items */
    public function createBill(Vendor $vendor, array $data, User $user): VendorBill
    {
        $accounts = Account::whereIn('id', array_column($data['items'], 'account_id'))->get()->keyBy('id');
        foreach ($data['items'] as $i => $item) {
            $a = $accounts[$item['account_id']] ?? null;
            if (! $a || $a->is_group || ! in_array($a->type, ['expense', 'asset'], true) || in_array($a->subtype, ['cash', 'bank', 'mfs', 'receivable'], true)) {
                throw ValidationException::withMessages(["items.$i.account_id" => 'Choose an expense or asset account.']);
            }
        }

        return DB::transaction(function () use ($vendor, $data, $user, $accounts) {
            $date = Carbon::parse($data['date']);
            $total = round(array_sum(array_map(fn ($i) => (float) $i['amount'], $data['items'])), 2);
            $bill = VendorBill::create([
                'bill_no' => $this->ids->next('vendor_bill', 'VB', 5, $date->year),
                'vendor_ref' => $data['vendor_ref'] ?? null, 'vendor_id' => $vendor->id, 'branch_id' => $data['branch_id'],
                'date' => $date, 'due_date' => $data['due_date'] ?? $date->copy()->addDays(30), 'total' => $total,
                'status' => 'unpaid', 'description' => $data['description'] ?? null, 'created_by' => $user->id,
            ]);
            foreach ($data['items'] as $item) {
                $bill->items()->create($item);
            }
            $entry = $this->ledger->post('vendor_bill.recorded', $date, $bill->branch_id, "{$bill->bill_no} — {$vendor->name}".($bill->vendor_ref ? " (bill {$bill->vendor_ref})" : ''), [
                ...array_map(fn ($i) => ['account' => $accounts[$i['account_id']], 'debit' => $i['amount'], 'memo' => $i['description']], $data['items']),
                ['account' => $this->accounts->system('payable'), 'credit' => $total, 'party' => $vendor],
            ], $bill, 'journal');
            $bill->update(['journal_entry_id' => $entry?->id]);

            return $bill->load('items.account');
        });
    }

    public function voidBill(VendorBill $bill, string $reason): VendorBill
    {
        if ((float) $bill->paid > 0 || $bill->status === 'void') {
            throw ValidationException::withMessages(['bill' => 'Only an unpaid bill can be voided.']);
        }

        return DB::transaction(function () use ($bill, $reason) {
            $this->ledger->reverseAllFor($bill, $reason);
            $bill->update(['status' => 'void']);

            return $bill;
        });
    }

    /** Pays a vendor; without allocations the oldest unpaid bills are paid first, the rest reduces opening balance. */
    public function pay(Vendor $vendor, array $data, User $user): VendorPayment
    {
        $from = Account::findOrFail($data['paid_from_account_id']);
        if (! in_array($from->subtype, ['cash', 'bank', 'mfs'], true) || $from->is_group) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Pay from a cash, bank or bKash/Nagad account.']);
        }
        $amount = round((float) $data['amount'], 2);
        if ($amount > $this->balance($vendor) + 0.001) {
            throw ValidationException::withMessages(['amount' => 'More than what is owed to this vendor (৳'.number_format($this->balance($vendor), 2).').']);
        }

        return DB::transaction(function () use ($vendor, $data, $user, $from, $amount) {
            $date = Carbon::parse($data['date']);
            $payment = VendorPayment::create([
                'payment_no' => $this->ids->next('vendor_payment', 'VP', 5, $date->year),
                'vendor_id' => $vendor->id, 'branch_id' => $data['branch_id'], 'date' => $date, 'amount' => $amount,
                'paid_from_account_id' => $from->id, 'reference' => $data['reference'] ?? null, 'created_by' => $user->id,
            ]);

            $left = $amount;
            foreach ($vendor->bills()->whereIn('status', ['unpaid', 'partially_paid'])->orderBy('date')->orderBy('id')->lockForUpdate()->get() as $bill) {
                if ($left <= 0) {
                    break;
                }
                $part = min($left, $bill->due());
                $payment->allocations()->create(['vendor_bill_id' => $bill->id, 'amount' => $part]);
                $paid = round((float) $bill->paid + $part, 2);
                $bill->update(['paid' => $paid, 'status' => $paid >= (float) $bill->total ? 'paid' : 'partially_paid']);
                $left = round($left - $part, 2);
            }

            $entry = $this->ledger->post('vendor_payment.made', $date, $payment->branch_id, "{$payment->payment_no} — paid {$vendor->name}", [
                ['account' => $this->accounts->system('payable'), 'debit' => $amount, 'party' => $vendor],
                ['account' => $from, 'credit' => $amount, 'memo' => $payment->reference],
            ], $payment, 'payment');
            $payment->update(['journal_entry_id' => $entry?->id]);

            return $payment;
        });
    }

    /** Owed to the vendor = opening + bills − payments (from the books, so it always matches the ledger). */
    public function balance(Vendor $vendor): float
    {
        $bills = (float) $vendor->bills()->where('status', '!=', 'void')->sum('total');
        $paid = (float) $vendor->payments()->sum('amount');

        return round((float) $vendor->opening_balance + $bills - $paid, 2);
    }
}
