<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts C (Accounts §৬, §৯, §১০, §১১, §১২): vendors and payables, bank reconciliation,
 * fixed assets with straight-line depreciation, budgets, and year-end closing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('supplier');      // landlord | supplier | utility | service | other
            $table->string('phone', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0); // owed at go-live
            $table->boolean('is_active')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 30)->unique();               // VB-2026-00001
            $table->string('vendor_ref', 100)->nullable();          // the supplier's own bill number
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->decimal('total', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid');        // unpaid | partially_paid | paid | void
            $table->string('description', 500)->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();        // expense or asset account
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 30)->unique();            // VP-2026-00001
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('paid_from_account_id')->constrained('accounts');
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();        // bank / bKash / Nagad account
            $table->date('statement_date');
            $table->decimal('statement_balance', 14, 2);
            $table->string('status', 12)->default('draft');         // draft | completed
            $table->decimal('book_balance', 14, 2)->nullable();     // frozen on completion
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description', 255);
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 14, 2);                       // + money in, − money out
            $table->foreignId('journal_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('unmatched');     // unmatched | matched | adjusted
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 20)->unique();             // FA-2026-001
            $table->string('name');
            $table->foreignId('account_id')->constrained();         // 1610 / 1620 / 1630 …
            $table->foreignId('branch_id')->constrained();
            $table->string('location')->nullable();
            $table->date('purchase_date');
            $table->decimal('cost', 12, 2);
            $table->decimal('salvage_value', 12, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('accumulated_depreciation', 12, 2)->default(0);
            $table->char('depreciated_until', 7)->nullable();       // YYYY-MM of the last month charged
            $table->string('status', 10)->default('active');        // active | disposed
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_amount', 12, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // null = whole center
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'branch_id']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('annual_amount', 14, 2);                // spread evenly across the 12 months
            $table->unique(['budget_id', 'account_id']);
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignId('closing_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closing_entry_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
        });
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('fixed_assets');
        Schema::table('journal_lines', fn (Blueprint $t) => $t->dropConstrainedForeignId('bank_reconciliation_id'));
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('vendor_payment_allocations');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bill_items');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('vendors');
    }
};
