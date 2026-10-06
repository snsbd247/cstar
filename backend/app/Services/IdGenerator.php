<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates gap-free, human-readable yearly codes, safe under concurrent requests
 * (row lock on id_sequences). Example: next('patient', 'CSTAR') => CSTAR-2026-00001.
 */
class IdGenerator
{
    /** With $withYear = false the number runs on across years (stored as year 0) and the year is left out of the code. */
    public function next(string $key, string $prefix, int $pad = 5, ?int $year = null, bool $withYear = true): string
    {
        $year = $withYear ? ($year ?? (int) now()->format('Y')) : 0;

        $value = DB::transaction(function () use ($key, $year) {
            DB::table('id_sequences')->insertOrIgnore([
                'key' => $key, 'year' => $year, 'last_value' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('id_sequences')
                ->where(['key' => $key, 'year' => $year])
                ->lockForUpdate()
                ->first();

            $next = $row->last_value + 1;

            DB::table('id_sequences')
                ->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        $number = str_pad((string) $value, $pad, '0', STR_PAD_LEFT);

        return $withYear ? sprintf('%s-%d-%s', $prefix, $year, $number) : "{$prefix}-{$number}";
    }
}
