<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Accounts rules editable by admins (group "accounts"). A5: vouchers up to ৳5,000 post without a second approver. */
class AccountSettings
{
    public const DEFAULTS = [
        'approval_limit' => '5000',
    ];

    private const CACHE_KEY = 'settings.accounts';

    /** @return array<string, string> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'accounts')->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key(array_filter($stored, fn ($v) => $v !== null), self::DEFAULTS));
    }

    public function approvalLimit(): float
    {
        return (float) $this->all()['approval_limit'];
    }

    /** @param  array<string, string|null>  $values */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::updateOrCreate(['group' => 'accounts', 'key' => $key], ['value' => $value ?? '']);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
