<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry core (Accounts §২, §১৩): chart of accounts, fiscal years (July–June, decision A1),
 * monthly periods, and journal entries/lines. Billing posts here from day one (§৩).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();          // 2026-27
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('open'); // open | closed
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('type', 10);                     // asset | liability | equity | income | expense
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false);    // groups only total their children; no postings
            $table->string('normal_balance', 6);            // debit | credit
            $table->string('subtype', 20)->nullable();      // cash | bank | mfs | receivable | payable | ...
            $table->string('system_key', 50)->nullable()->unique(); // used by auto-posting; cannot be deleted
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();  // per-branch cash box
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete(); // income per therapy service
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->string('voucher_type', 10)->default('system'); // receipt | payment | journal | contra | system
            $table->string('event', 40)->nullable()->index();      // invoice.issued, payment.received ...
            $table->date('date')->index();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fiscal_year_id')->constrained();
            $table->foreignId('accounting_period_id')->constrained();
            $table->string('narration', 500)->nullable();
            $table->nullableMorphs('source');
            $table->string('status', 10)->default('posted'); // posted | reversed
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('party'); // patient | vendor | employee
            $table->string('memo', 255)->nullable();
            $table->index(['account_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
    }
};
