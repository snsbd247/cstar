<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Go-live checklist (Sprint 17): shown in Settings → System and by `php artisan cstar:go-live-check`.
 * "server" checks are about the hosting set-up; "data" checks are about the center's real information.
 */
class GoLiveChecks
{
    public function __construct(private BackupService $backups, private SiteSettings $site, private SystemSettings $system) {}

    /** @return list<array{group: string, label: string, ok: bool, hint: string}> */
    public function all(): array
    {
        $heartbeat = Cache::get('scheduler.heartbeat');
        $lastBackup = $this->backups->list()[0] ?? null;
        $demoUsers = User::where('email', 'like', '%@cstar.test')->count();
        $center = $this->system->group('center');
        $messaging = app(MessagingSettings::class);

        return [
            ['group' => 'server', 'label' => 'Debug mode is off (APP_DEBUG=false)', 'ok' => ! config('app.debug'), 'hint' => 'Error details must never be shown to visitors.'],
            ['group' => 'server', 'label' => 'Running in production mode (APP_ENV=production)', 'ok' => app()->isProduction(), 'hint' => 'Set on the live server; local and test copies show "local".'],
            ['group' => 'server', 'label' => 'Site address uses HTTPS', 'ok' => str_starts_with((string) config('app.url'), 'https://'), 'hint' => 'Turn on cPanel AutoSSL and set APP_URL to https://…'],
            ['group' => 'server', 'label' => 'Sign-in cookie is HTTPS-only', 'ok' => (bool) config('session.secure'), 'hint' => 'SESSION_SECURE_COOKIE=true on the live server.'],
            ['group' => 'server', 'label' => 'Scheduler (cron) is running', 'ok' => $heartbeat && Carbon::parse($heartbeat)->gt(now()->subMinutes(5)), 'hint' => 'cPanel cron: * * * * * php artisan schedule:run'],
            ['group' => 'server', 'label' => 'A database backup exists from the last 2 days', 'ok' => $lastBackup && Carbon::parse($lastBackup['created_at'])->gt(now()->subDays(2)), 'hint' => 'Turn on the daily backup or press "Back up now".'],
            ['group' => 'server', 'label' => 'SMS goes out through GreenWeb (not test mode)', 'ok' => $messaging->flag('sms_enabled') && $messaging->get('sms_driver') === 'greenweb', 'hint' => 'Settings → SMS & WhatsApp: GreenWeb token, then send a test SMS.'],
            ['group' => 'server', 'label' => 'Email sending is configured', 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'hint' => 'Set MAIL_* in .env (cPanel email account).'],
            ['group' => 'data', 'label' => 'No demo accounts (…@cstar.test)', 'ok' => $demoUsers === 0, 'hint' => "{$demoUsers} demo account(s) — deactivate before go-live."],
            ['group' => 'data', 'label' => 'Center information filled in (address and phone)', 'ok' => $center['address'] !== '' && $center['phone'] !== '', 'hint' => 'Settings → Center Information — printed on every PDF.'],
            ['group' => 'data', 'label' => 'Every active branch has an address and phone', 'ok' => ! Branch::where('is_active', true)->where(fn ($q) => $q->whereNull('address')->orWhereNull('phone'))->exists(), 'hint' => 'Branches → edit each branch.'],
            ['group' => 'data', 'label' => 'Opening balances entered', 'ok' => JournalEntry::where('event', GoLiveService::EVENT)->where('status', 'posted')->exists(), 'hint' => 'Settings → Go-live → Opening balances (decision A9).'],
            ['group' => 'data', 'label' => 'Website open to search engines', 'ok' => $this->site->get('seo_noindex') !== '1', 'hint' => 'Website / CMS → SEO — turn off "Hide from search engines" on go-live day.'],
        ];
    }
}
