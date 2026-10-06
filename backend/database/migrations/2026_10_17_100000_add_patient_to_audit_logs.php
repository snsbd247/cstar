<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activity logs (Sprint 16): each log row remembers which child it concerns, so
 * "Patient Activity" can show everything that happened to one child in one list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('auditable_id')->constrained()->nullOnDelete();
            $table->index(['action', 'created_at']);
        });

        // Backfill rows written before this column existed.
        DB::table('audit_logs')->where('auditable_type', 'App\Models\Patient')->update(['patient_id' => DB::raw('auditable_id')]);
        foreach (DB::table('audit_logs')->whereNull('patient_id')->whereNotNull('auditable_type')->distinct()->pluck('auditable_type') as $type) {
            if (! class_exists($type)) {
                continue;
            }
            $table = (new $type)->getTable();
            if (Schema::hasColumn($table, 'patient_id')) {
                DB::table('audit_logs')->where('auditable_type', $type)->whereNull('patient_id')->update([
                    'patient_id' => DB::raw("(SELECT t.patient_id FROM `{$table}` t WHERE t.id = audit_logs.auditable_id)"),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
