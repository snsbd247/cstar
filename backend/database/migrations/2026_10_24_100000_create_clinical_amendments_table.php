<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 22 (Plan §২১, #১০): a finalized clinical note is never edited — it is amended, with a reason and the old text kept. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_amendments', function (Blueprint $table) {
            $table->id();
            $table->morphs('amendable');                                    // therapy session, assessment, training record
            $table->string('field', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('reason', 500);
            $table->foreignId('amended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_amendments');
    }
};
