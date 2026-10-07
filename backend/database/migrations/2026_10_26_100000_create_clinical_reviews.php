<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 22 (Plan #২০): a senior therapist (clinical supervisor) reviews finalized notes and assessments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->boolean('is_supervisor')->default(false)->after('status');
        });
        Schema::create('clinical_reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');                                   // therapy session or assessment
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 15);                                  // ok | needs_changes
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_reviews');
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn('is_supervisor');
        });
    }
};
