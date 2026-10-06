<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 9 — Assessment (Plan §১৯): first step of every child's journey; recommendations lead to enrollments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            // Structured findings: [{key, label}] — e.g. receptive language, expressive language, articulation …
            $table->json('sections');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_code', 30)->unique(); // ASM-2026-00001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('therapist_id')->constrained()->restrictOnDelete(); // assessor
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->text('chief_complaint')->nullable(); // parents' main concern
            $table->text('background')->nullable();
            $table->json('section_findings')->nullable(); // {section_key: text}
            $table->text('summary')->nullable();          // overall impression
            $table->text('recommendations')->nullable();  // narrative
            $table->text('parent_summary')->nullable();   // plain language for the family
            $table->string('status', 10)->default('draft'); // draft | final
            $table->boolean('shared_with_parent')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'date']);
        });

        // Structured "what the child needs" — reception turns each into an enrollment.
        Schema::create('assessment_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('enrollment_type', 20); // training | therapy
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency')->nullable(); // "2 sessions / week", "Daily class"
            $table->string('priority', 10)->default('normal'); // high | normal | low
            $table->string('note')->nullable();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete(); // set when acted on
            $table->timestamps();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('source_assessment_id')->nullable()->after('end_note')->constrained('assessments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_assessment_id'));
        Schema::dropIfExists('assessment_recommendations');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_types');
    }
};
