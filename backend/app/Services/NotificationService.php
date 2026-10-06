<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what (Plan §৩৪). Parents get Bangla messages about their own child only;
 * staff get English messages about work waiting for them in their branch.
 */
class NotificationService
{
    public const DEFAULTS = [
        'email_enabled' => '1',          // also email people who have an address
        'parent_reminders' => '1',       // reminder the evening before an appointment
        'staff_reminders' => '1',        // morning list of session notes still to finalize
    ];

    /** @return array<string, string> */
    public function settings(): array
    {
        $stored = Cache::rememberForever('settings.notifications', fn () => Setting::where('group', 'notifications')->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public function updateSettings(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::updateOrCreate(['group' => 'notifications', 'key' => $key], ['value' => $value ? '1' : '0']);
        }
        Cache::forget('settings.notifications');
    }

    public function enabled(string $key): bool
    {
        return ($this->settings()[$key] ?? '0') === '1';
    }

    /** @param  iterable<User>|User  $users */
    public function send(iterable|User $users, string $kind, string $title, string $body, string $url): int
    {
        $users = collect($users instanceof User ? [$users] : $users)->filter()->unique('id')
            ->filter(fn (User $u) => $u->status === UserStatus::Active)->values();
        if ($users->isNotEmpty()) {
            Notification::send($users, new AppNotification($kind, $title, $body, $url, $this->enabled('email_enabled')));
        }

        return $users->count();
    }

    /** Parent logins of a child — only guardians allowed into the portal. */
    public function parentsOf(Patient $patient): Collection
    {
        return User::whereHas('guardian.patients', fn ($q) => $q->whereKey($patient->id)->where('guardian_patient.can_access_portal', true))->get();
    }

    public function toParents(Patient $patient, string $kind, string $title, string $body, string $url = '/portal'): int
    {
        return $this->send($this->parentsOf($patient), $kind, $title, $body, $url);
    }

    /** Staff with a permission in a branch (super admins always included). */
    public function staffWith(string $permission, ?int $branchId, ?int $exceptUserId = null): Collection
    {
        return User::permission($permission)->where('status', UserStatus::Active)
            ->where(fn ($q) => $q->when($branchId, fn ($w) => $w->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
                ->orWhereHas('roles', fn ($r) => $r->where('name', Role::SuperAdmin->value)))
            ->when($exceptUserId, fn ($q) => $q->whereKeyNot($exceptUserId))
            ->get();
    }

    public function toStaff(string $permission, ?int $branchId, string $kind, string $title, string $body, string $url, ?int $exceptUserId = null): int
    {
        return $this->send($this->staffWith($permission, $branchId, $exceptUserId), $kind, $title, $body, $url);
    }

    /** "৳1,500" with Bangla digits for parent messages. */
    public static function bnTaka(float $amount): string
    {
        return '৳'.strtr(number_format($amount, $amount == floor($amount) ? 0 : 2), array_combine(range(0, 9), ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']));
    }

    public static function bnDate(\DateTimeInterface $date): string
    {
        $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
        $digits = array_combine(range(0, 9), ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']);

        return strtr($date->format('j'), $digits).' '.$months[(int) $date->format('n') - 1];
    }

    public static function bnTime(string $hhmm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $part = $h < 12 ? 'সকাল' : ($h < 15 ? 'দুপুর' : ($h < 18 ? 'বিকাল' : 'সন্ধ্যা'));
        $digits = array_combine(range(0, 9), ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']);

        return $part.' '.strtr(($h % 12 ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT), $digits);
    }
}
