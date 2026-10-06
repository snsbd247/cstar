<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts B — payroll (Accounts §৭). Everyone who is paid is an employee (A4); trainers and therapists
 * link to their employee record, so TRAINER ≠ THERAPIST stays intact while pay is handled in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->unique();         // EMP-0001
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('department', 20);                      // therapist | trainer | admin | support
            $table->foreignId('branch_id')->constrained();
            $table->date('joining_date');
            $table->date('left_date')->nullable();
            $table->string('employment_type', 20)->default('full_time'); // full_time | part_time | visiting
            $table->string('pay_type', 20)->default('fixed');      // fixed | per_session | mixed | revenue_share (A3)
            $table->string('phone', 20)->nullable();
            $table->string('payment_method', 10)->default('bank'); // cash | bank | bkash | nagad
            $table->string('bank_name')->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('mfs_number', 20)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('active');        // active | inactive | left
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('trainers', fn (Blueprint $t) => $t->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete());
        Schema::table('therapists', fn (Blueprint $t) => $t->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete());

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('house_rent', 12, 2)->default(0);
            $table->decimal('medical', 12, 2)->default(0);
            $table->decimal('conveyance', 12, 2)->default(0);
            $table->json('other_allowances')->nullable();          // [{name, amount}]
            $table->unsignedSmallInteger('included_sessions')->default(0); // mixed: sessions covered by the fixed pay
            $table->decimal('revenue_share_percent', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('session_pay_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete(); // null = any service
            $table->decimal('rate', 10, 2);
            $table->date('effective_from');
            $table->timestamps();
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->decimal('installment', 12, 2);
            $table->decimal('balance', 12, 2);                     // still to recover
            $table->foreignId('paid_from_account_id')->constrained('accounts');
            $table->string('reason', 500)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('active');       // active | settled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_no', 30)->unique();                 // PR-2026-10-HQ
            $table->char('month', 7);                               // YYYY-MM
            $table->foreignId('branch_id')->constrained();
            $table->string('type', 10)->default('salary');          // salary | bonus
            $table->string('title')->nullable();                    // e.g. "Eid-ul-Adha bonus"
            $table->string('status', 10)->default('draft');         // draft | posted | paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['month', 'branch_id', 'type', 'title']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->string('department', 20);
            $table->decimal('fixed_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('session_count')->default(0);
            $table->decimal('session_pay', 12, 2)->default(0);      // per-session, extra sessions or revenue share
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('other_addition', 12, 2)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->json('breakdown')->nullable();                  // allowances, sessions per service, advances
            $table->string('note', 500)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payment_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('session_pay_rates');
        Schema::dropIfExists('salary_structures');
        Schema::table('therapists', fn (Blueprint $t) => $t->dropConstrainedForeignId('employee_id'));
        Schema::table('trainers', fn (Blueprint $t) => $t->dropConstrainedForeignId('employee_id'));
        Schema::dropIfExists('employees');
    }
};
