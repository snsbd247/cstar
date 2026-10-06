<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fixed assets (Accounts §১০): register (bought now from cash/bank, or already owned at go-live),
 * straight-line monthly depreciation posted as one journal per month, and disposal with gain/loss.
 */
class FixedAssetService
{
    public function __construct(private IdGenerator $ids, private LedgerService $ledger, private AccountMap $accounts) {}

    public function register(array $data, User $user): FixedAsset
    {
        $account = Account::findOrFail($data['account_id']);
        if ($account->subtype !== 'fixed_asset') {
            throw ValidationException::withMessages(['account_id' => 'Choose a fixed asset category (furniture, equipment, computer …).']);
        }

        return DB::transaction(function () use ($data, $user, $account) {
            $date = Carbon::parse($data['purchase_date']);
            $asset = FixedAsset::create([
                ...collect($data)->only(['name', 'account_id', 'branch_id', 'location', 'purchase_date', 'cost', 'salvage_value', 'useful_life_months', 'notes'])->all(),
                'asset_code' => $this->ids->next('fixed_asset', 'FA', 3, $date->year),
                'accumulated_depreciation' => $data['accumulated_depreciation'] ?? 0,
                'depreciated_until' => isset($data['accumulated_depreciation']) && (float) $data['accumulated_depreciation'] > 0 ? today()->subMonth()->format('Y-m') : null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            // Bought now: Dr asset / Cr cash-bank. Already owned (go-live): recorded by the opening journal instead.
            if (! empty($data['paid_from_account_id'])) {
                $from = Account::findOrFail($data['paid_from_account_id']);
                $entry = $this->ledger->post('asset.purchased', $date, $asset->branch_id, "{$asset->asset_code} — bought {$asset->name}", [
                    ['account' => $account, 'debit' => $asset->cost, 'memo' => $asset->name],
                    ['account' => $from, 'credit' => $asset->cost],
                ], $asset, 'payment');
                $asset->update(['journal_entry_id' => $entry?->id]);
            }

            return $asset;
        });
    }

    /** Charges depreciation for every active asset up to the end of $month (catching up missed months). */
    public function depreciate(Carbon $month, User $user): array
    {
        $upTo = $month->copy()->startOfMonth();
        $lines = [];
        $total = 0;

        return DB::transaction(function () use ($upTo, &$lines, &$total) {
            foreach (FixedAsset::where('status', 'active')->whereDate('purchase_date', '<=', $upTo->copy()->endOfMonth())->lockForUpdate()->get() as $asset) {
                $from = $asset->depreciated_until
                    ? Carbon::createFromFormat('Y-m-d', $asset->depreciated_until.'-01')->addMonth()
                    : $asset->purchase_date->copy()->startOfMonth();
                $months = 0;
                for ($m = $from->copy(); $m->lte($upTo); $m->addMonth()) {
                    $months++;
                }
                $amount = min($asset->depreciable(), round($months * $asset->monthlyDepreciation(), 2));
                if ($months === 0 || $amount <= 0) {
                    continue;
                }
                $asset->update(['accumulated_depreciation' => round((float) $asset->accumulated_depreciation + $amount, 2), 'depreciated_until' => $upTo->format('Y-m')]);
                $lines[] = ['account' => $this->accounts->system('depreciation'), 'debit' => $amount, 'branch_id' => $asset->branch_id, 'memo' => "{$asset->asset_code} {$asset->name} ({$months} mo)"];
                $lines[] = ['account' => $this->accounts->system('accumulated_depreciation'), 'credit' => $amount, 'branch_id' => $asset->branch_id, 'memo' => $asset->asset_code];
                $total += $amount;
            }

            $entry = $lines ? $this->ledger->post('asset.depreciation', $upTo->copy()->endOfMonth()->isFuture() ? today() : $upTo->copy()->endOfMonth(), null,
                'Depreciation to '.$upTo->format('F Y'), $lines, null, 'journal') : null;

            return ['assets' => intdiv(count($lines), 2), 'amount' => round($total, 2), 'voucher_no' => $entry?->voucher_no];
        });
    }

    /** Sale or write-off: remove cost and depreciation, book any gain (other income) or loss (misc expense). */
    public function dispose(FixedAsset $asset, array $data, User $user): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw ValidationException::withMessages(['asset' => 'This asset is already disposed.']);
        }

        return DB::transaction(function () use ($asset, $data) {
            $date = Carbon::parse($data['date']);
            $received = round((float) ($data['amount'] ?? 0), 2);
            $book = $asset->bookValue();
            $gain = round($received - $book, 2);
            $lines = [
                ['account' => $this->accounts->system('accumulated_depreciation'), 'debit' => $asset->accumulated_depreciation],
                ['account' => $asset->account, 'credit' => $asset->cost, 'memo' => $asset->name],
            ];
            if ($received > 0) {
                $lines[] = ['account' => Account::findOrFail($data['received_in_account_id']), 'debit' => $received];
            }
            if ($gain > 0) {
                $lines[] = ['account' => $this->accounts->system('income_other'), 'credit' => $gain, 'memo' => 'Gain on disposal'];
            } elseif ($gain < 0) {
                $lines[] = ['account' => Account::where('code', '5990')->firstOrFail(), 'debit' => -$gain, 'memo' => 'Loss on disposal'];
            }
            $this->ledger->post('asset.disposed', $date, $asset->branch_id, "{$asset->asset_code} — {$asset->name} disposed".($data['reason'] ?? null ? ": {$data['reason']}" : ''), $lines, $asset, 'journal');
            $asset->update(['status' => 'disposed', 'disposed_at' => $date, 'disposal_amount' => $received]);

            return $asset;
        });
    }
}
