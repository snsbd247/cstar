<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (Plan §৫ঝ, §১৯): packages, invoices, payments with allocations.
 * An issued invoice is never deleted — only voided with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->foreignId('service_id')->constrained();
            $table->unsignedSmallInteger('sessions_count');
            $table->unsignedSmallInteger('validity_days');
            $table->decimal('price', 12, 2);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // null = all branches
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->nullable()->unique(); // assigned when issued
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('due_total', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft | issued | partially_paid | paid | void
            $table->string('discount_reason', 255)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
            $table->index(['branch_id', 'status', 'issue_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20); // admission | assessment | training_fee | therapy_session | package | consultation | other
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('therapy_session_id')->nullable()->constrained()->nullOnDelete();
            $table->char('billing_period', 7)->nullable(); // YYYY-MM for monthly training fees
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index(['enrollment_id', 'billing_period']);
        });

        Schema::create('patient_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->unsignedSmallInteger('total_sessions');
            $table->unsignedSmallInteger('used_sessions')->default(0); // cache of package_usages
            $table->decimal('price', 12, 2);                             // net of the line's discount
            $table->date('start_date');
            $table->date('expiry_date');
            $table->string('status', 20)->default('active'); // active | exhausted | expired | cancelled
            $table->timestamps();
            $table->index(['patient_id', 'service_id', 'status']);
        });

        Schema::create('package_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('therapy_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 20); // session | no_show | late_cancel | adjustment
            $table->smallInteger('quantity'); // +1 uses a session, −1 gives one back
            $table->decimal('value', 12, 2);  // revenue recognised by this usage
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['patient_package_id', 'appointment_id', 'reason']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('patient_package_id')->nullable()->after('package_id')->constrained()->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->string('type', 10)->default('payment'); // payment | refund
            $table->decimal('amount', 12, 2);
            $table->string('method', 10); // cash | bkash | nagad | bank | card
            $table->string('transaction_ref', 100)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 10)->default('completed'); // completed | void
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
            $table->index(['branch_id', 'paid_at']);
            $table->index(['received_by', 'paid_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->boolean('from_advance')->default(false); // applied later from money already held as advance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('patient_package_id'));
        Schema::dropIfExists('package_usages');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('packages');
    }
};
