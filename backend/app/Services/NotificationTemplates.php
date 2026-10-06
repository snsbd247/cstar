<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Notifications → Templates (Sprint 16): the wording of every automatic message, editable by the center.
 * {placeholders} are filled when the message is sent; an empty override falls back to the default text.
 * Parent messages are in Bangla (decision D7), staff messages in English.
 */
class NotificationTemplates
{
    /** key => [label, audience, default title, default body, placeholders] */
    public const TEMPLATES = [
        'appointment.booked' => ['New appointment', 'parents', 'নতুন অ্যাপয়েন্টমেন্ট', '{service} — {date}, {time}, {therapist}', ['child', 'service', 'date', 'time', 'therapist']],
        'appointment.reminder' => ['Appointment reminder (evening before)', 'parents', 'আগামীকাল অ্যাপয়েন্টমেন্ট', '{service} — {time}, {therapist}', ['child', 'service', 'time', 'therapist']],
        'appointment.cancelled' => ['Appointment cancelled', 'parents', 'অ্যাপয়েন্টমেন্ট বাতিল', '{service} — {date}{reason}', ['child', 'service', 'date', 'reason']],
        'invoice.issued' => ['New bill', 'parents', 'নতুন বিল', '{invoice_no} — {amount} বকেয়া', ['child', 'invoice_no', 'amount']],
        'payment.received' => ['Payment received', 'parents', 'পেমেন্ট গ্রহণ করা হয়েছে', '{amount} পাওয়া গেছে — রসিদ {receipt_no}। ধন্যবাদ।', ['child', 'amount', 'receipt_no']],
        'assessment.shared' => ['Report shared', 'parents', 'নতুন রিপোর্ট', '{report} রিপোর্ট দেখা ও ডাউনলোড করা যাবে।', ['child', 'report']],
        'package.low' => ['Package almost used up', 'parents', 'প্যাকেজ প্রায় শেষ', '{package} — আর মাত্র {left}টি সেশন বাকি।', ['child', 'package', 'left']],
        'package.finished' => ['Package used up', 'parents', 'প্যাকেজ শেষ', '{package} — সব সেশন ব্যবহার হয়েছে। নবায়নের জন্য রিসেপশনে যোগাযোগ করুন।', ['child', 'package']],
        'package.renewal' => ['Package renewal (front desk)', 'staff', 'Package renewal: {child}', '{package} — {left} sessions left', ['child', 'package', 'left']],
        'voucher.submitted' => ['Voucher waiting approval', 'staff', 'Voucher {voucher_no} needs approval', '{amount} — {narration} (by {by})', ['voucher_no', 'amount', 'narration', 'by']],
        'payroll.prepared' => ['Payroll ready for approval', 'staff', '{payroll} is ready for approval', '{amount} net for {staff_count} staff (prepared by {by})', ['payroll', 'amount', 'staff_count', 'by']],
        'cash.closed' => ['Cash closed — receive it', 'staff', '{by} closed their cash', 'Counted {amount}{difference}. Please receive it.', ['by', 'amount', 'difference']],
    ];

    private const CACHE_KEY = 'settings.notification_templates';

    /** @return array<string, array{title: string, body: string}> saved overrides */
    public function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('group', 'notification_templates')->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true) ?: [])->all());
    }

    /** @return array{title: string, body: string} */
    public function render(string $key, array $vars): array
    {
        [, , $title, $body] = self::TEMPLATES[$key];
        $custom = $this->overrides()[$key] ?? [];
        $fill = fn (string $text) => trim(preg_replace('/\s{2,}/u', ' ', strtr($text, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all())));

        return ['title' => $fill(($custom['title'] ?? '') ?: $title), 'body' => $fill(($custom['body'] ?? '') ?: $body)];
    }

    /** @return list<array{key: string, label: string, audience: string, title: string, body: string, default_title: string, default_body: string, placeholders: list<string>}> */
    public function all(): array
    {
        $overrides = $this->overrides();

        return collect(self::TEMPLATES)->map(fn ($t, $key) => [
            'key' => $key, 'label' => $t[0], 'audience' => $t[1],
            'title' => $overrides[$key]['title'] ?? '', 'body' => $overrides[$key]['body'] ?? '',
            'default_title' => $t[2], 'default_body' => $t[3], 'placeholders' => $t[4],
        ])->values()->all();
    }

    public function save(string $key, ?string $title, ?string $body): void
    {
        $title = trim((string) $title);
        $body = trim((string) $body);
        if ($title === '' && $body === '') {
            Setting::where('group', 'notification_templates')->where('key', $key)->delete();
        } else {
            Setting::updateOrCreate(['group' => 'notification_templates', 'key' => $key], ['value' => json_encode(['title' => $title, 'body' => $body], JSON_UNESCAPED_UNICODE)]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
