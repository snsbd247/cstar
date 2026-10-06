<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        // Gap-free yearly sequences for human-readable codes (CSTAR-2026-00001, INV-2026-000001 ...).
        Schema::create('id_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['key', 'year']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30)->index(); // created | updated | deleted | viewed | exported | login | logout | login_failed
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('id_sequences');
        Schema::dropIfExists('settings');
    }
};
