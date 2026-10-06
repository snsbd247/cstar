<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core tables enrollments depend on. Their management screens arrive in Sprint 7 (trainers, classes)
 * and Sprint 8 (therapists, schedules); later sprints add columns rather than new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One catalogue for website, booking, enrollments, packages and invoices.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20)->index(); // therapy | training | assessment | consultation
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->unsignedSmallInteger('default_duration_min')->nullable();
            $table->decimal('default_price', 12, 2)->nullable();
            $table->boolean('is_bookable_online')->default(false);
            $table->boolean('show_on_website')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_bn')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // TRAINER ≠ THERAPIST: two separate tables, each optionally linked to a login.
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('employee_code', 30)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('show_on_website')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('therapists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('primary_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('employee_code', 30)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('designation')->nullable();
            $table->string('therapist_type', 30)->index(); // slt | ot | aba | opt | special_educator | other
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('show_on_website')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Which therapy services a therapist delivers (enrollment + booking filter).
        Schema::create('service_therapist', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapist_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_id', 'therapist_id']);
        });

        // UI label: "Class". `class` is a reserved word in PHP, hence TrainingGroup.
        Schema::create('training_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('max_students')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | inactive | closed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_groups');
        Schema::dropIfExists('service_therapist');
        Schema::dropIfExists('therapists');
        Schema::dropIfExists('trainers');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('services');
    }
};
