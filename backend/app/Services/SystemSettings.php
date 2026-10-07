<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin Settings (Sprint 16) — one store for the groups on the Settings page. Every key here is read
 * somewhere in the app; nothing is stored only for show. Billing, accounts, notification and website
 * settings keep their own services (BillingSettings, AccountSettings, …).
 */
class SystemSettings
{
    public const GROUPS = [
        'general' => [
            'center_short_name' => 'C-STAR',
            'center_full_name' => 'Center for Speech Therapy & Autism Rehabilitation',
        ],
        'center' => [
            'legal_name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'registration_no' => '',
            'tin' => '',
        ],
        'patient_id' => [
            'prefix' => 'CSTAR',
            'digits' => '5',
            'include_year' => '1',
            'include_branch_code' => '0',
        ],
        'appointment' => [
            'late_cancel_hours' => '24',          // decision D3
            'recurring_weeks' => '4',             // weekly therapy slots booked this far ahead
            'portal_requests_enabled' => '1',     // parents may ask for an appointment from the portal
            'portal_booking_enabled' => '1',      // Sprint 20: therapy families pick a free slot themselves (pending until confirmed)
            'portal_booking_days' => '14',         // how far ahead
            'portal_booking_notice_hours' => '12', // not closer than this to the start
            'portal_booking_max_open' => '2',      // unconfirmed portal bookings per child at a time
            'portal_cancel_enabled' => '1',       // parents may cancel upcoming appointments (late-cancel rule applies)
        ],
        'staff_attendance' => [               // Sprint 20
            'self_check_in' => '1',               // staff check themselves in / out from the dashboard
            'office_start' => '09:00',
            'late_after_minutes' => '15',
            'weekly_off' => '5',                  // Carbon weekday (5 = Friday) or 'none'
        ],
        'pdf' => [
            'paper_size' => 'A4',
            'brand_color' => '#047857',
            'footer_note' => "Confidential — for the child's family and treating team only.",
            'show_printed_date' => '1',
        ],
        'security' => [
            'session_timeout_minutes' => '120',
            'password_min_length' => '8',
            'password_require_symbol' => '0',
            'login_attempts_per_minute' => '5',
        ],
        'backup' => [
            'daily_enabled' => '1',
            'keep_days' => '14',
        ],
    ];

    private const CACHE_KEY = 'settings.system';

    /** Validation per group, used by the controller. */
    public static function rules(string $group): array
    {
        $bool = ['required', 'in:0,1'];

        return match ($group) {
            'general' => [
                'center_short_name' => ['required', 'string', 'max:30'],
                'center_full_name' => ['required', 'string', 'max:120'],
            ],
            'center' => [
                'legal_name' => ['nullable', 'string', 'max:150'], 'address' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:120'],
                'website' => ['nullable', 'string', 'max:120'], 'registration_no' => ['nullable', 'string', 'max:60'],
                'tin' => ['nullable', 'string', 'max:40'],
            ],
            'patient_id' => [
                'prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
                'digits' => ['required', 'integer', 'between:3,8'],
                'include_year' => $bool, 'include_branch_code' => $bool,
            ],
            'appointment' => [
                'late_cancel_hours' => ['required', 'integer', 'between:0,168'],
                'recurring_weeks' => ['required', 'integer', 'between:1,12'],
                'portal_requests_enabled' => $bool,
                'portal_booking_enabled' => $bool, 'portal_cancel_enabled' => $bool,
                'portal_booking_days' => ['required', 'integer', 'between:1,60'],
                'portal_booking_notice_hours' => ['required', 'integer', 'between:0,72'],
                'portal_booking_max_open' => ['required', 'integer', 'between:1,10'],
            ],
            'staff_attendance' => [
                'self_check_in' => $bool,
                'office_start' => ['required', 'date_format:H:i'],
                'late_after_minutes' => ['required', 'integer', 'between:0,180'],
                'weekly_off' => ['required', Rule::in(['0', '1', '2', '3', '4', '5', '6', 'none'])],
            ],
            'pdf' => [
                'paper_size' => ['required', Rule::in(['A4', 'Letter', 'Legal'])],
                'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'footer_note' => ['nullable', 'string', 'max:200'],
                'show_printed_date' => $bool,
            ],
            'security' => [
                'session_timeout_minutes' => ['required', 'integer', 'between:10,720'],
                'password_min_length' => ['required', 'integer', 'between:8,32'],
                'password_require_symbol' => $bool,
                'login_attempts_per_minute' => ['required', 'integer', 'between:3,20'],
            ],
            'backup' => [
                'daily_enabled' => $bool,
                'keep_days' => ['required', 'integer', 'between:1,90'],
            ],
            default => [],
        };
    }

    /** @return array<string, array<string, string>> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::whereIn('group', array_keys(self::GROUPS))
            ->get(['group', 'key', 'value'])->groupBy('group')->map(fn ($rows) => $rows->pluck('value', 'key')->all())->all());

        $all = [];
        foreach (self::GROUPS as $group => $defaults) {
            $all[$group] = array_merge($defaults, array_intersect_key(array_filter($stored[$group] ?? [], fn ($v) => $v !== null), $defaults));
        }

        return $all;
    }

    public function group(string $group): array
    {
        return $this->all()[$group] ?? [];
    }

    public function get(string $group, string $key): string
    {
        return (string) ($this->all()[$group][$key] ?? self::GROUPS[$group][$key] ?? '');
    }

    public function int(string $group, string $key): int
    {
        return (int) $this->get($group, $key);
    }

    public function flag(string $group, string $key): bool
    {
        return $this->get($group, $key) === '1';
    }

    /**
     * For code that runs before the database may exist (boot, middleware during install):
     * falls back to the default instead of failing.
     */
    public static function safe(string $group, string $key): string
    {
        try {
            return app(self::class)->get($group, $key);
        } catch (Throwable) {
            return self::GROUPS[$group][$key];
        }
    }

    /** @param  array<string, string|int|null>  $values */
    public function update(string $group, array $values): void
    {
        foreach (array_intersect_key($values, self::GROUPS[$group]) as $key => $value) {
            Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => (string) ($value ?? '')]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
