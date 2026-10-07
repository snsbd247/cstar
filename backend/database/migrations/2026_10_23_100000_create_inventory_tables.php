<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20: therapy materials and supplies per branch. Stock only — what was paid for them is booked through
 * Expenses / Vendor bills as before, so the ledger is not touched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('category', 30);                                 // therapy_material | toy | stationery | cleaning | medical | other
            $table->string('unit', 20)->default('pcs');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);                                     // in | out | adjust
            $table->decimal('quantity', 12, 2);                             // signed change in stock
            $table->decimal('balance_after', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference', 100)->nullable();                   // bill no., who took it, …
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
