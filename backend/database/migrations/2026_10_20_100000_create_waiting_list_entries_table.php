<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 20: children waiting for a seat in a class or a therapist's slot. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);                                    // training | therapy
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preferred_time', 10)->default('any');          // morning | afternoon | evening | any
            $table->string('priority', 10)->default('normal');            // high | normal
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('waiting');             // waiting | offered | enrolled | removed
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'type', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');
    }
};
