<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Billing rules editable by admins (group "billing"). Defaults follow decisions D3, D4 and Plan §৩৬. */
class BillingSettings
{
    public const DEFAULTS = [
        'invoice_due_days' => '7',
        'receptionist_discount_limit_percent' => '10', // larger discounts need a branch admin / accountant
        'no_show_deducts_package' => '1',               // D3: a no-show uses a package session
        'late_cancel_deducts_package' => '1',           // D3: cancelling within 24 h uses one, earlier does not
        'admission_fee' => '',                          // quick-add amount on a new invoice
        'training_monthly_fee' => '',                   // used when an enrollment has no fee of its own
    ];

    private const CACHE_KEY = 'settings.billing';

    /** @return array<string, string> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'billing')->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key(array_filter($stored, fn ($v) => $v !== null), self::DEFAULTS));
    }

    public function get(string $key): string
    {
        return (string) ($this->all()[$key] ?? '');
    }

    public function flag(string $key): bool
    {
        return $this->get($key) === '1';
    }

    /** @param  array<string, string|null>  $values */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::updateOrCreate(['group' => 'billing', 'key' => $key], ['value' => $value ?? '']);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
