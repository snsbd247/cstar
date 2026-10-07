<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Settings → SMS & WhatsApp (Sprint 18). Gateway tokens are stored encrypted with APP_KEY and never
 * sent back to the browser — the screen only learns whether one is saved.
 */
class MessagingSettings
{
    public const DEFAULTS = [
        'sms_enabled' => '0',
        'sms_driver' => 'log',                 // log (test mode — nothing leaves the server) | greenweb
        'greenweb_token' => '',                // encrypted
        'whatsapp_enabled' => '0',
        'whatsapp_driver' => 'log',            // log | whatsapp_cloud
        'whatsapp_phone_number_id' => '',
        'whatsapp_token' => '',                // encrypted
        'whatsapp_template' => 'cstar_notice', // approved template with one body parameter {{1}}
        'whatsapp_language' => 'bn',
        'prefix' => 'C-STAR',                  // first word of every SMS so families know who wrote
        'kinds' => '["appointment.booked","appointment.reminder","appointment.cancelled","invoice.issued","payment.received","assessment.shared","package.low","package.finished","announcement"]',
        'staff_sms' => '0',                    // staff get in-app + email only unless this is on
    ];

    public const SECRETS = ['greenweb_token', 'whatsapp_token'];

    private const CACHE_KEY = 'settings.messaging';

    /** @return array<string, string> raw values (secrets still encrypted) */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'messaging')->pluck('value', 'key')->all());
    }

    public function get(string $key): string
    {
        $value = (string) ($this->stored()[$key] ?? self::DEFAULTS[$key] ?? '');
        if (in_array($key, self::SECRETS, true) && $value !== '') {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return ''; // APP_KEY changed — the token has to be entered again
            }
        }

        return $value;
    }

    public function flag(string $key): bool
    {
        return $this->get($key) === '1';
    }

    /** @return list<string> notification kinds that also go out by SMS / WhatsApp */
    public function kinds(): array
    {
        return json_decode($this->get('kinds'), true) ?: [];
    }

    /** Safe for the screen: secrets replaced by "is one saved?". */
    public function forScreen(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = in_array($key, self::SECRETS, true) ? '' : $this->get($key);
        }
        $out['kinds'] = $this->kinds();
        foreach (self::SECRETS as $key) {
            $out[$key.'_saved'] = $this->get($key) !== '';
        }

        return $out;
    }

    /** @param  array<string, mixed>  $values  empty secret = keep the saved one */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                if ($value === null || $value === '') {
                    continue;
                }
                $value = Crypt::encryptString((string) $value);
            } elseif ($key === 'kinds') {
                $value = json_encode(array_values((array) $value));
            }
            Setting::updateOrCreate(['group' => 'messaging', 'key' => $key], ['value' => (string) $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public function clearSecret(string $key): void
    {
        Setting::where('group', 'messaging')->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }
}
