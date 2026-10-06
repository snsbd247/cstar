<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FixedAssetService;
use App\Services\LedgerService;
use App\Services\PayablesService;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 15 — ALL FIGURES ARE SAMPLES:
 * three vendors (one bill part-paid), fixed assets (go-live equipment + a computer bought now),
 * this fiscal year's budget, and a bank reconciliation in progress.
 */
class DemoAccountsCSeeder extends Seeder
{
    public function run(PayablesService $payables, FixedAssetService $assets, LedgerService $ledger): void
    {
        if (Vendor::exists()) {
            return;
        }
        $accountant = User::where('email', 'accounts@cstar.test')->firstOrFail();
        $branch = Branch::where('code', 'HQ')->first() ?? Branch::firstOrFail();
        auth()->setUser($accountant);
        $code = fn (string $c) => Account::where('code', $c)->firstOrFail();
        $bank = $code('1131');
        $first = today()->startOfMonth();

        // Vendors.
        $toys = $payables->createVendor(['name' => 'Shishu Khelna Ghar (demo)', 'type' => 'supplier', 'phone' => '01811000000', 'address' => 'New Market, Dhaka'], $accountant);
        $payables->createVendor(['name' => 'Building owner (demo)', 'type' => 'landlord', 'phone' => '01911000000'], $accountant);
        $isp = $payables->createVendor(['name' => 'Fast Net ISP (demo)', 'type' => 'utility', 'opening_balance' => 2500], $accountant);
        $payables->createBill($toys, ['date' => $first->copy()->addDays(2)->toDateString(), 'branch_id' => $branch->id, 'vendor_ref' => 'SKG-2231',
            'description' => 'Therapy materials', 'items' => [
                ['account_id' => $code('5500')->id, 'description' => 'Sensory balls and textured mats', 'amount' => 8500],
                ['account_id' => $code('5500')->id, 'description' => 'Picture cards (PECS) set', 'amount' => 3200],
            ]], $accountant);
        $payables->pay($toys, ['date' => today()->toDateString(), 'branch_id' => $branch->id, 'amount' => 5000, 'paid_from_account_id' => $bank->id, 'reference' => 'bKash TRX demo'], $accountant);
        $payables->createBill($isp, ['date' => $first->toDateString(), 'branch_id' => $branch->id, 'due_date' => $first->copy()->addDays(10)->toDateString(),
            'items' => [['account_id' => $code('5430')->id, 'description' => 'Internet — '.$first->format('F Y'), 'amount' => 2500]]], $accountant);

        // Fixed assets: equipment already owned at go-live (in the opening balance) + a computer bought now.
        foreach ([['Sensory integration room equipment', 90000, 60, 'Therapy room 1'], ['Speech therapy kits and mirrors', 60000, 48, 'Therapy room 2']] as [$name, $cost, $life, $loc]) {
            $assets->register(['name' => $name, 'account_id' => $code('1620')->id, 'branch_id' => $branch->id, 'location' => $loc,
                'purchase_date' => $first->toDateString(), 'cost' => $cost, 'salvage_value' => 0, 'useful_life_months' => $life], $accountant);
        }
        $assets->register(['name' => 'Front desk computer', 'account_id' => $code('1630')->id, 'branch_id' => $branch->id, 'location' => 'Reception',
            'purchase_date' => $first->copy()->addDays(1)->toDateString(), 'cost' => 48000, 'salvage_value' => 6000, 'useful_life_months' => 36,
            'paid_from_account_id' => $bank->id], $accountant);

        // Budget for this fiscal year (whole center).
        $ledger->openPeriodFor(today());
        $fy = FiscalYear::whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->firstOrFail();
        $budget = Budget::create(['fiscal_year_id' => $fy->id, 'branch_id' => null, 'name' => "Center budget {$fy->name} (demo)", 'created_by' => $accountant->id]);
        foreach (['4300' => 420000, '4410' => 300000, '4420' => 150000, '4100' => 60000, '5300' => 540000, '5110' => 480000, '5120' => 360000,
            '5130' => 1100000, '5140' => 300000, '5410' => 60000, '5430' => 30000, '5500' => 120000] as $c => $amount) {
            $account = Account::where('code', $c)->first();
            $account && $budget->lines()->create(['account_id' => $account->id, 'annual_amount' => $amount]);
        }

        // Bank reconciliation in progress: statement with the opening deposit and a bank charge.
        $rec = BankReconciliation::create(['account_id' => $bank->id, 'statement_date' => today()->subDay(), 'statement_balance' => 499885, 'status' => 'draft', 'created_by' => $accountant->id]);
        $rec->lines()->create(['date' => $first, 'description' => 'Opening balance brought forward', 'amount' => 500000, 'status' => 'unmatched']);
        $rec->lines()->create(['date' => today()->subDays(2), 'description' => 'SMS & account maintenance fee', 'amount' => -115, 'status' => 'unmatched']);
    }
}
