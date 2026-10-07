<?php

namespace App\Services;

use App\Enums\ServiceCategory;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Service;
use RuntimeException;

/**
 * Resolves which ledger account an auto-posting uses (Accounts §৩).
 * System accounts are found by `system_key`; each branch gets its own cash box on first use.
 */
class AccountMap
{
    /** @var array<string, Account> */
    private array $cache = [];

    public function system(string $key): Account
    {
        return $this->cache[$key] ??= Account::where('system_key', $key)->first()
            ?? throw new RuntimeException("System account '{$key}' is missing — run ChartOfAccountsSeeder.");
    }

    /** "Cash in Hand — <branch>", created under 1110 the first time the branch takes cash. */
    public function cashFor(int $branchId): Account
    {
        $key = "cash_branch_{$branchId}";
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $group = $this->system('cash_group');
        $branch = Branch::withTrashed()->findOrFail($branchId);
        $account = Account::where('subtype', 'cash')->where('branch_id', $branchId)->where('parent_id', $group->id)->first();
        if (! $account) {
            $next = (int) Account::where('parent_id', $group->id)->max('code') ?: (int) $group->code;
            $account = Account::create([
                'code' => (string) ($next + 1), 'name' => "Cash in Hand — {$branch->name}", 'name_bn' => $branch->name_bn ? "হাতে নগদ — {$branch->name_bn}" : null,
                'type' => 'asset', 'normal_balance' => 'debit', 'parent_id' => $group->id, 'subtype' => 'cash',
                'branch_id' => $branchId, 'is_system' => true,
            ]);
        }

        return $this->cache[$key] = $account;
    }

    /** Where a payment method's money lands. Card settlements go to the main bank account. */
    public function forMethod(string $method, int $branchId): Account
    {
        return match ($method) {
            'cash' => $this->cashFor($branchId),
            'bkash' => $this->system('mfs_bkash'),
            'nagad' => $this->system('mfs_nagad'),
            'bank', 'card' => $this->system('bank_main'),
            // SSLCommerz pays out to the bank a few days later, less its fee — matched in Bank Reconciliation.
            'online' => $this->system('online_clearing'),
            default => throw new RuntimeException("Unknown payment method '{$method}'."),
        };
    }

    /** Income account for an invoice line (package lines go to unearned revenue instead). */
    public function incomeFor(string $itemType, ?Service $service): Account
    {
        return match ($itemType) {
            'admission' => $this->system('income_admission'),
            'assessment' => $this->system('income_assessment'),
            'training_fee' => $this->system('income_training'),
            'consultation' => $this->system('income_consultation'),
            'package' => $this->system('unearned_package'),
            // Dues from before go-live were income of the old books; here they only open the receivable.
            'opening_balance' => $this->system('retained_earnings'),
            'therapy_session' => $this->therapyIncome($service),
            default => $service?->category === ServiceCategory::Therapy ? $this->therapyIncome($service) : $this->system('income_other'),
        };
    }

    public function therapyIncome(?Service $service): Account
    {
        return ($service ? Account::where('service_id', $service->id)->where('type', 'income')->first() : null)
            ?? $this->system('income_therapy_other');
    }
}
