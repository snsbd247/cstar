<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19: one row per attempt to pay online from the parent portal. Money is booked (payments row)
 * only after the gateway confirms it server-to-server — never because the browser came back "success".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tran_id', 30)->unique();          // ours, sent to the gateway
            $table->string('gateway', 15);                      // sslcommerz | bkash | test
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // the parent who paid
            $table->decimal('amount', 12, 2);
            $table->string('status', 12)->default('initiated'); // initiated | paid | failed | cancelled | review
            $table->string('gateway_ref', 100)->nullable();     // SSLCommerz val_id / bKash paymentID
            $table->string('gateway_trx', 100)->nullable();     // bank_tran_id / bKash trxID
            $table->string('instrument', 50)->nullable();       // card type / bKash / Nagad …
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error', 500)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
