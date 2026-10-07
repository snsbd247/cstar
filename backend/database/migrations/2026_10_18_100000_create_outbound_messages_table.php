<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 18: every SMS / WhatsApp message — what was sent, to whom, through which gateway and what happened. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);            // sms | whatsapp
            $table->string('driver', 20);             // log | greenweb | whatsapp_cloud
            $table->string('to', 20);
            $table->text('body');
            $table->unsignedTinyInteger('segments')->default(1);
            $table->string('kind', 40)->nullable();   // notification template key, announcement, test …
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('queued'); // queued | sent | failed
            $table->string('provider_ref', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->text('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
