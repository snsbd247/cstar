<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 20: parents tell the therapist, day by day, whether the home practice was done. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_practice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapy_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10);                                   // done | partly | not_done
            $table->string('comment', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['therapy_session_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_practice_logs');
    }
};
