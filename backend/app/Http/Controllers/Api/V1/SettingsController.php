<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\PatientService;
use App\Services\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Settings page (Sprint 16): grouped settings, system health and database backups — Super Admin only. */
class SettingsController extends Controller
{
    public function index(SystemSettings $settings, PatientService $patients): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $branch = Branch::orderBy('id')->first();

        return response()->json(['data' => [
            'groups' => $settings->all(),
            'patient_id_example' => $branch ? $patients->nextCode($branch->id, preview: true) : null,
        ]]);
    }

    public function update(Request $request, string $group, SystemSettings $settings, PatientService $patients): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        abort_unless(isset(SystemSettings::GROUPS[$group]), 404);
        $data = $request->validate(SystemSettings::rules($group));

        $before = $settings->group($group);
        $settings->update($group, $data);
        $after = $settings->group($group);
        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed) {
            AuditLogger::log('settings.updated', null, ['group' => $group, ...array_intersect_key($before, array_flip($changed))], ['group' => $group, ...array_intersect_key($after, array_flip($changed))]);
        }
        $branch = Branch::orderBy('id')->first();

        return response()->json(['data' => [
            'groups' => $settings->all(),
            'patient_id_example' => $branch ? $patients->nextCode($branch->id, preview: true) : null,
        ]]);
    }

    /** Settings → System: versions, scheduler, mail, storage and a go-live checklist. */
    public function system(BackupService $backups): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $heartbeat = Cache::get('scheduler.heartbeat');
        $lastBackup = $backups->list()[0] ?? null;
        $demoUsers = User::where('email', 'like', '%@cstar.test')->count();
        $production = app()->isProduction();

        $checks = [
            ['label' => 'Debug mode is off (APP_DEBUG=false)', 'ok' => ! config('app.debug'), 'hint' => 'Error details must never be shown to visitors.'],
            ['label' => 'Running in production mode (APP_ENV=production)', 'ok' => $production, 'hint' => 'Set on the live server; local and test copies show "local".'],
            ['label' => 'Site address uses HTTPS', 'ok' => str_starts_with((string) config('app.url'), 'https://'), 'hint' => 'Turn on cPanel AutoSSL and set APP_URL to https://…'],
            ['label' => 'Sign-in cookie is HTTPS-only', 'ok' => (bool) config('session.secure'), 'hint' => 'SESSION_SECURE_COOKIE=true on the live server.'],
            ['label' => 'Scheduler (cron) is running', 'ok' => $heartbeat && Carbon::parse($heartbeat)->gt(now()->subMinutes(5)), 'hint' => 'cPanel cron: * * * * * php artisan schedule:run'],
            ['label' => 'A database backup exists from the last 2 days', 'ok' => $lastBackup && Carbon::parse($lastBackup['created_at'])->gt(now()->subDays(2)), 'hint' => 'Turn on the daily backup or press "Back up now".'],
            ['label' => 'No demo accounts (…@cstar.test)', 'ok' => $demoUsers === 0, 'hint' => "{$demoUsers} demo account(s) — deactivate before go-live."],
            ['label' => 'Email sending is configured', 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'hint' => 'Set MAIL_* in .env (cPanel email account).'],
        ];

        return response()->json(['data' => [
            'app' => [
                'environment' => app()->environment(), 'debug' => (bool) config('app.debug'), 'url' => config('app.url'),
                'timezone' => config('app.timezone'), 'laravel' => app()->version(), 'php' => PHP_VERSION,
                'database' => DB::selectOne('select version() as v')->v, 'mail' => config('mail.default'),
                'queue' => config('queue.default'), 'cache' => config('cache.default'),
            ],
            'scheduler_heartbeat' => $heartbeat,
            'last_backup' => $lastBackup,
            'storage' => [
                'documents_bytes' => $this->folderSize(storage_path('app/private')) - $this->folderSize($backups->directory()),
                'backups_bytes' => $this->folderSize($backups->directory()),
            ],
            'checks' => $checks,
        ]]);
    }

    public function clearCache(): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        Artisan::call('optimize:clear');
        AuditLogger::log('cache.cleared');

        return response()->json(['message' => 'Caches cleared.']);
    }

    public function backups(BackupService $backups): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);

        return response()->json(['data' => $backups->list()]);
    }

    public function createBackup(BackupService $backups, SystemSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        @set_time_limit(300);
        $file = $backups->create();
        $backups->prune($settings->int('backup', 'keep_days'));
        AuditLogger::log('backup.created', null, new: ['file' => $file['name']]);

        return response()->json(['data' => $file], 201);
    }

    public function downloadBackup(string $name, BackupService $backups): BinaryFileResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $path = $backups->path($name);
        AuditLogger::log('backup.downloaded', null, new: ['file' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    public function deleteBackup(string $name, BackupService $backups): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $backups->delete($name);
        AuditLogger::log('backup.deleted', null, new: ['file' => $name]);

        return response()->json(['message' => 'Backup deleted.']);
    }

    private function folderSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
