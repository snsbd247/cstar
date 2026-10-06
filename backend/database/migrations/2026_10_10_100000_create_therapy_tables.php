<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8 — Therapy. Kept apart from training:
 * THERAPY APPOINTMENT ≠ STUDENT ATTENDANCE, THERAPY SESSION ≠ TRAINING SESSION.
 */
return new class extends Migration
{
    public function up(): void
    {
        // When and where a therapist sees patients.
        Schema::create('therapist_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(45);
            $table->timestamps();
            $table->index(['therapist_id', 'weekday']);
        });

        Schema::create('therapist_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['therapist_id', 'start_date', 'end_date']);
        });

        // Regular weekly slot of a therapy enrollment, e.g. Speech every Sunday & Tuesday 16:00.
        Schema::create('therapy_enrollment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->timestamps();
            $table->unique(['enrollment_id', 'weekday']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_code', 30)->unique(); // APT-2026-00001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete(); // therapy enrollment (none for a first assessment)
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('therapist_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('type', 20)->default('therapy'); // assessment | therapy | consultation | follow_up
            $table->string('status', 20)->default('confirmed'); // pending | confirmed | checked_in | completed | cancelled | no_show | rescheduled
            $table->string('source', 20)->default('front_desk'); // front_desk | phone | online | recurring
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->boolean('is_late_cancellation')->default(false);
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('appointment_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Database-level guard against double booking: one live appointment per therapist per start time.
            $table->string('active_slot', 60)->nullable()->storedAs(
                "IF(status IN ('cancelled','no_show','rescheduled'), NULL, CONCAT(therapist_id, '|', date, '|', start_time))"
            );
            $table->unique('active_slot');
            $table->index(['therapist_id', 'date']);
            $table->index(['patient_id', 'date']);
            $table->index(['branch_id', 'date', 'status']);
        });

        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('therapist_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->text('goals_worked')->nullable();
            $table->text('observation')->nullable();
            $table->text('patient_response')->nullable();
            $table->text('progress')->nullable();
            $table->text('challenges')->nullable();
            $table->text('home_practice')->nullable();
            $table->text('next_session_plan')->nullable();
            $table->text('therapist_notes')->nullable(); // internal, clinical
            $table->text('parent_summary')->nullable();  // plain-language, for parents
            $table->string('status', 10)->default('draft'); // draft | final
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'date']);
            $table->index(['therapist_id', 'date']);
        });

        Schema::create('activity_type_therapy_session', function (Blueprint $table) {
            $table->foreignId('therapy_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->primary(['therapy_session_id', 'activity_type_id']);
        });

        Schema::table('appointment_requests', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('appointment_id'));
        Schema::dropIfExists('activity_type_therapy_session');
        Schema::dropIfExists('therapy_sessions');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('therapy_enrollment_slots');
        Schema::dropIfExists('therapist_leaves');
        Schema::dropIfExists('therapist_schedules');
    }
};
