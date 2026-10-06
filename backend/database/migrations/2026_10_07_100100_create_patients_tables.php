<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PATIENT ≠ STUDENT: every child is a patient; "student" is derived from training enrollments.
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 30)->unique(); // CSTAR-2026-00001
            $table->foreignId('home_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('photo_path')->nullable(); // private disk
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('phone', 20)->index(); // main family contact
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->string('referral_source', 50)->nullable(); // doctor | website | facebook | parent | school | other
            $table->string('referred_by')->nullable();
            $table->date('registration_date');
            $table->string('status', 20)->default('active')->index(); // active | on_hold | discharged | inactive
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['phone', 'date_of_birth']); // duplicate detection
            $table->index('name');
        });

        // Sensitive clinical data kept apart so access can be granted separately.
        Schema::create('patient_clinical_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('diagnosis_notes')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('developmental_history')->nullable();
            $table->text('previous_therapy')->nullable();
            $table->text('medications')->nullable();
            $table->text('allergies')->nullable();
            $table->text('school_info')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnosis_patient', function (Blueprint $table) {
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnosis_id')->constrained()->restrictOnDelete();
            $table->primary(['patient_id', 'diagnosis_id']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // parent portal login
            $table->string('name');
            $table->string('phone', 20)->index();
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('nid', 30)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Siblings can share a guardian; a child can have several guardians.
        Schema::create('guardian_patient', function (Blueprint $table) {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 20); // father | mother | grandparent | sibling | uncle | aunt | other
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('can_access_portal')->default(false);
            $table->timestamps();
            $table->primary(['guardian_id', 'patient_id']);
        });

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30); // medical_report | prescription | previous_assessment | consent_form | id_document | other
            $table->string('title');
            $table->string('path'); // private disk, never public
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->boolean('visible_to_parent')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // treatment | photo_media | data_sharing
            $table->boolean('granted');
            $table->date('signed_on');
            $table->foreignId('document_id')->nullable()->constrained('patient_documents')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('guardian_patient');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('diagnosis_patient');
        Schema::dropIfExists('patient_clinical_profiles');
        Schema::dropIfExists('patients');
    }
};
