<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts A (Accounts §৪, §৫, §৮): vouchers with maker-checker approval, expenses
 * (category → expense account), recurring expenses and the end-of-day cash closing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();          // PV-2026-00045
            $table->string('type', 10);                          // payment | receipt | journal | contra
            $table->date('date');
            $table->foreignId('branch_id')->constrained();
            $table->string('narration', 500);
            $table->decimal('amount', 14, 2);                    // Σ debit (= Σ credit)
            $table->string('status', 12)->default('draft');      // draft | submitted | posted | rejected | reversed
            $table->string('attachment_path')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'branch_id']);
            $table->index(['branch_id', 'date']);
        });

        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->foreignId('account_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no', 30)->unique();          // EXP-2026-00001
            $table->date('date');
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->foreignId('paid_from_account_id')->constrained('accounts');
            $table->string('payee')->nullable();                 // vendors arrive with Accounts C
            $table->string('reference', 100)->nullable();        // bill no., transaction ID
            $table->string('description', 500)->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurring_expense_id')->nullable();
            $table->string('status', 12)->default('posted');     // draft | submitted | posted | rejected | reversed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('day_of_month');
            $table->foreignId('paid_from_account_id')->constrained('accounts');
            $table->string('payee')->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('last_generated_period', 7)->nullable(); // YYYY-MM
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('recurring_expense_id')->references('id')->on('recurring_expenses')->nullOnDelete();
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();         // the receptionist closing their cash
            $table->date('date');
            $table->json('expected');                            // per method: {cash, bkash, nagad, bank, card}
            $table->decimal('expected_cash', 12, 2);
            $table->decimal('counted_cash', 12, 2);
            $table->json('denominations')->nullable();           // {"1000":5,"500":3,...}
            $table->decimal('difference', 12, 2);                // counted − expected (negative = short)
            $table->string('reason', 500)->nullable();
            $table->string('status', 12)->default('submitted');  // submitted | received
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete(); // short/over posting
            $table->timestamps();
            $table->unique(['user_id', 'branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
        Schema::table('expenses', fn (Blueprint $t) => $t->dropForeign(['recurring_expense_id']));
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('voucher_lines');
        Schema::dropIfExists('vouchers');
    }
};
