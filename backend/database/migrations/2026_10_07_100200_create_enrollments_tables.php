<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PATIENT 1:N ENROLLMENT, each enrollment extended 1:1 by exactly one type table
 * (class-table inheritance). Required fields per type are NOT NULL in the type table:
 *   training → class + trainer      therapy → service + therapist
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_code', 30)->unique(); // ENR-2026-00001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // training | therapy
            $table->string('status', 20)->default('active'); // pending | active | on_hold | completed | discontinued
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('end_reason', 30)->nullable(); // goals_achieved | dropout | transferred_out | financial | relocated | other
            $table->text('end_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'type', 'status']);
            $table->index(['branch_id', 'type', 'status']);
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('training_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_fee', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('therapy_enrollments', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('therapist_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sessions_per_week')->nullable();
            $table->unsignedSmallInteger('session_duration_min')->nullable();
            $table->string('billing_mode', 20)->default('per_session'); // package | per_session | monthly
            $table->timestamps();
        });

        // Who was responsible, when — drives reports and "former trainer loses access".
        Schema::create('enrollment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('training_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('therapist_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Unified patient history feed (registration, enrollments, later sessions, assessments ...).
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 10)->default('internal'); // internal | parent
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('enrollment_assignments');
        Schema::dropIfExists('therapy_enrollments');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('enrollments');
    }
};
