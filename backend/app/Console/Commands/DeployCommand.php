<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoLiveChecks;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\AssessmentTypeSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * One command after every upload to cPanel (docs/C-STAR-Deployment-BN.md):
 * checks the server, migrates, adds new reference data, caches and links storage. Safe to run again.
 * On the first install it seeds everything; later it never resets edited roles, website text or settings.
 */
class DeployCommand extends Command
{
    protected $signature = 'cstar:deploy {--skip-checks : Do not stop on a failed server check}';

    protected $description = 'Install or update C-STAR on the server (migrate, reference data, caches)';

    private const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'zlib', 'curl', 'bcmath'];

    public function handle(): int
    {
        $this->info('C-STAR deploy — '.now()->toDateTimeString());

        if (! $this->serverChecks() && ! $this->option('skip-checks')) {
            $this->error('Fix the items above and run "php artisan cstar:deploy" again.');

            return self::FAILURE;
        }

        if (blank(config('app.key'))) {
            $this->call('key:generate', ['--force' => true]);
            $this->warn('A new APP_KEY was written to .env — keep a copy of .env safe; without it nobody can sign in.');
        }

        // First install = never installed before (marker) and nobody can sign in yet.
        $firstInstall = ! Schema::hasTable('settings')
            || (! Setting::where('group', 'system')->where('key', 'installed_at')->exists() && User::count() === 0);
        $this->call('migrate', ['--force' => true]);

        if ($firstInstall) {
            $this->info('First install: roles, branches, services, chart of accounts …');
            $this->call('db:seed', ['--force' => true]);
            Setting::updateOrCreate(['group' => 'system', 'key' => 'installed_at'], ['value' => now()->toIso8601String()]);
        } else {
            $this->info('Update: adding new reference data only (edited roles, website text and settings are kept).');
            foreach ([ChartOfAccountsSeeder::class, ActivityTypeSeeder::class, AssessmentTypeSeeder::class, ExpenseCategorySeeder::class] as $seeder) {
                $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
            }
            $this->syncNewPermissions();
        }

        // Public uploads (website photos) — patient documents stay private and never get a public link.
        file_exists(public_path('storage')) ? $this->line('  storage link already in place') : $this->call('storage:link');
        $this->call('optimize:clear');
        $this->call('optimize');

        $this->newLine();
        $this->call('cstar:go-live-check');

        if (User::role(Role::SuperAdmin->value)->doesntExist()) {
            $this->newLine();
            $this->warn('No Super Admin yet — create the first one: php artisan cstar:create-admin');
        }

        return self::SUCCESS;
    }

    private function serverChecks(): bool
    {
        $ok = true;
        $line = function (bool $pass, string $text) use (&$ok) {
            $ok = $ok && $pass;
            $this->line(($pass ? '  <fg=green>✔</>' : '  <fg=red>✘</>').' '.$text);
        };

        $line(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP '.PHP_VERSION.' (8.3 or newer needed — cPanel → MultiPHP Manager)');
        foreach (self::EXTENSIONS as $ext) {
            $line(extension_loaded($ext), "PHP extension {$ext}");
        }
        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $line(is_writable(base_path($dir)), "{$dir} is writable");
        }
        try {
            DB::connection()->getPdo();
            $line(true, 'Database connection ('.config('database.connections.mysql.database').')');
        } catch (Throwable $e) {
            $line(false, 'Database connection — check DB_* in .env: '.$e->getMessage());
        }
        $line(is_file(public_path('spa/index.html')), 'Staff & parent app files (public_html/spa)');
        $line(is_file(public_path('build/manifest.json')), 'Website style files (public_html/build)');

        return $ok;
    }

    /** New permissions from this release go to the roles that have them by default; nothing is taken away. */
    private function syncNewPermissions(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $existing = PermissionModel::pluck('name')->all();
        $new = array_values(array_diff(\App\Enums\Permission::all(), $existing));
        foreach ($new as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }
        foreach (Role::cases() as $role) {
            $model = RoleModel::findOrCreate($role->value, 'web');
            $grant = array_values(array_intersect($new, $role->defaultPermissions()));
            if ($grant) {
                $model->givePermissionTo($grant);
            }
        }
        $registrar->forgetCachedPermissions();
        $this->info($new ? 'New permissions added: '.implode(', ', $new) : 'Permissions: nothing new.');
    }
}
