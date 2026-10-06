<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use RuntimeException;

/**
 * Database backup (Plan §২১ "Backup") written in PHP, so it works on cPanel without mysqldump.
 * Files: storage/app/private/backups/cstar-db-YYYYMMDD-HHMMSS.sql.gz — outside public_html, downloaded
 * only by Super Admin through the API. Restore: import the .sql.gz in cPanel → phpMyAdmin.
 * Uploaded documents and photos are covered by cPanel's own home-directory backup.
 */
class BackupService
{
    private const PATTERN = '/^cstar-db-\d{8}-\d{6}\.sql\.gz$/';

    public function directory(): string
    {
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** @return array{name: string, size: int, created_at: string} */
    public function create(): array
    {
        $name = 'cstar-db-'.now()->format('Ymd-His').'.sql.gz';
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Cannot write the backup file.');
        }

        try {
            gzwrite($gz, "-- C-STAR database backup\n-- Created ".now()->toDateTimeString().' ('.config('app.timezone').")\n\n");
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

            $tables = array_map(fn ($row) => array_values((array) $row)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
            foreach ($tables as $table) {
                $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $batch = [];
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                    if (count($batch) === 200) {
                        gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            gzclose($gz);
        }

        $info = ['name' => $name, 'size' => filesize($path), 'created_at' => now()->toIso8601String()];
        Cache::forever('backup.last', $info);

        return $info;
    }

    /** @return list<array{name: string, size: int, created_at: string}> */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'cstar-db-*.sql.gz') ?: [];
        rsort($files);

        return array_map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'created_at' => Carbon::createFromTimestamp(filemtime($f))->toIso8601String()], $files);
    }

    /** Full path of a backup, refusing anything that is not one of our file names (no path tricks). */
    public function path(string $name): string
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! preg_match(self::PATTERN, $name) || ! is_file($path)) {
            throw ValidationException::withMessages(['backup' => 'No such backup.']);
        }

        return $path;
    }

    public function delete(string $name): void
    {
        unlink($this->path($name));
    }

    /** Removes backups older than the number of days in Settings → Backup; the newest one is always kept. */
    public function prune(int $keepDays): int
    {
        $removed = 0;
        foreach (array_slice($this->list(), 1) as $file) {
            if (Carbon::parse($file['created_at'])->lt(now()->subDays($keepDays))) {
                unlink($this->path($file['name']));
                $removed++;
            }
        }

        return $removed;
    }
}
