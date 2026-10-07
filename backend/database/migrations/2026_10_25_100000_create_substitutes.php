<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 (Plan #৬): when a therapist is away their appointments move to a substitute (the original is remembered);
 * when a trainer is away another trainer takes the class for those days with the same access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('substitute_for_id')->nullable()->after('therapist_id')->constrained('therapists')->nullOnDelete();
        });
        Schema::create('class_substitutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['trainer_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_substitutes');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_for_id');
        });
    }
};
