<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7 — Regular Training. Kept apart from therapy:
 * TRAINING SESSION ≠ THERAPY SESSION, STUDENT ATTENDANCE ≠ THERAPY APPOINTMENT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek)
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['training_group_id', 'weekday']);
        });

        // Daily attendance of regular students (never mixed with therapy appointments).
        Schema::create('training_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete(); // a training enrollment
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_group_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 10); // present | absent | late | leave | holiday
            $table->time('arrival_time')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'date']);
            $table->index(['training_group_id', 'date']);
            $table->index(['patient_id', 'date']);
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('applies_to', 10)->default('both'); // training | therapy | both
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One class sitting on one day.
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('theme')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['training_group_id', 'date']);
        });

        // One student's record inside a class sitting.
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->text('goals_worked')->nullable();
            $table->text('observation')->nullable();
            $table->unsignedTinyInteger('performance')->nullable(); // 1–5
            $table->text('progress')->nullable();
            $table->text('challenges')->nullable();
            $table->text('trainer_notes')->nullable(); // internal
            $table->text('parent_note')->nullable();   // shown to parents
            $table->text('next_plan')->nullable();
            $table->string('status', 10)->default('draft'); // draft | final
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'enrollment_id']);
            $table->index(['patient_id', 'date']);
        });

        Schema::create('activity_type_training_record', function (Blueprint $table) {
            $table->foreignId('training_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->primary(['training_record_id', 'activity_type_id']);
        });

        // Individual plan (ITP for training; therapy plans reuse it in Sprint 9) — belongs to an enrollment.
        Schema::create('individual_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->date('review_date')->nullable();
            $table->string('status', 10)->default('active'); // draft | active | closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plan_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_plan_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 50)->nullable(); // fine motor, communication …
            $table->string('title');
            $table->text('target')->nullable();
            $table->text('baseline_level')->nullable();
            $table->text('current_level')->nullable();
            $table->text('activities')->nullable();
            $table->string('measurement')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('review_date')->nullable();
            $table->string('status', 15)->default('in_progress'); // not_started | in_progress | achieved | discontinued
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('goal_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_goal_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source'); // training_record (therapy_session later)
            $table->date('date');
            $table->unsignedTinyInteger('score'); // 1–5 for the day
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['plan_goal_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_progress_entries');
        Schema::dropIfExists('plan_goals');
        Schema::dropIfExists('individual_plans');
        Schema::dropIfExists('activity_type_training_record');
        Schema::dropIfExists('training_records');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('training_attendance');
        Schema::dropIfExists('training_group_schedules');
    }
};
