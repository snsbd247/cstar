<?php

namespace App\Services\OnlinePayment;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Settings → Online Payment (Sprint 19). Merchant passwords and secrets are stored encrypted and never sent
 * back to the browser. "Sandbox" switches use the gateways' test servers (no real money).
 */
class OnlinePaymentSettings
{
    public const DEFAULTS = [
        'sslcommerz_enabled' => '0',
        'sslcommerz_sandbox' => '1',
        'sslcommerz_store_id' => '',
        'sslcommerz_store_password' => '',  // encrypted
        'bkash_enabled' => '0',
        'bkash_sandbox' => '1',
        'bkash_app_key' => '',
        'bkash_app_secret' => '',           // encrypted
        'bkash_username' => '',
        'bkash_password' => '',             // encrypted
        'test_enabled' => '0',              // pretend gateway for training copies — refused in production
        'min_amount' => '10',
    ];

    public const SECRETS = ['sslcommerz_store_password', 'bkash_app_secret', 'bkash_password'];

    private const CACHE_KEY = 'settings.online_payment';

    public function get(string $key): string
    {
        $value = (string) (Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'online_payment')->pluck('value', 'key')->all())[$key]
            ?? self::DEFAULTS[$key] ?? '');
        if (in_array($key, self::SECRETS, true) && $value !== '') {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return '';
            }
        }

        return $value;
    }

    public function flag(string $key): bool
    {
        return $this->get($key) === '1';
    }

    /** Gateways a parent can choose right now. */
    public function available(): array
    {
        return array_values(array_filter([
            $this->flag('bkash_enabled') ? 'bkash' : null,
            $this->flag('sslcommerz_enabled') ? 'sslcommerz' : null,
            $this->flag('test_enabled') && ! app()->isProduction() ? 'test' : null,
        ]));
    }

    public function forScreen(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = in_array($key, self::SECRETS, true) ? '' : $this->get($key);
        }
        foreach (self::SECRETS as $key) {
            $out[$key.'_saved'] = $this->get($key) !== '';
        }
        $out['production'] = app()->isProduction();

        return $out;
    }

    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                if ($value === null || $value === '') {
                    continue;
                }
                $value = Crypt::encryptString((string) $value);
            }
            Setting::updateOrCreate(['group' => 'online_payment', 'key' => $key], ['value' => (string) ($value ?? '')]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
