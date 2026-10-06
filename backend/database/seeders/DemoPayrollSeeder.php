<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\User;
use App\Services\IdGenerator;
use App\Services\PayrollService;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 12 — ALL SALARIES ARE SAMPLES:
 * ten staff across all four pay types, one salary advance, and last month's payroll approved and paid.
 */
class DemoPayrollSeeder extends Seeder
{
    public function run(IdGenerator $ids, PayrollService $payroll): void
    {
        if (Employee::exists()) {
            return;
        }
        $branch = Branch::where('code', 'HQ')->first() ?? Branch::firstOrFail();
        $accountant = User::where('email', 'accounts@cstar.test')->firstOrFail();
        $admin = User::where('email', 'branchadmin@cstar.test')->firstOrFail();
        auth()->setUser($accountant);
        $joined = today()->subYear()->startOfMonth();
        $user = fn (string $email) => User::where('email', $email)->value('id');

        $staff = [
            // name, designation, department, pay type, employment, user, profile, structure, rate
            ['Branch Admin', 'Center Manager', 'admin', 'fixed', 'full_time', $user('branchadmin@cstar.test'), null, ['basic' => 30000, 'house_rent' => 12000, 'medical' => 3000, 'conveyance' => 2000], null],
            ['Receptionist', 'Front Desk Officer', 'admin', 'fixed', 'full_time', $user('reception@cstar.test'), null, ['basic' => 12000, 'house_rent' => 5000, 'conveyance' => 1500], null],
            ['Accountant', 'Accounts Officer', 'admin', 'fixed', 'full_time', $user('accounts@cstar.test'), null, ['basic' => 20000, 'house_rent' => 8000, 'medical' => 2000], null],
            ['Imran Hossain', 'Speech & Language Therapist', 'therapist', 'mixed', 'full_time', $user('therapist@cstar.test'), Therapist::where('name', 'Imran Hossain')->first(), ['basic' => 28000, 'house_rent' => 10000, 'medical' => 2000, 'included_sessions' => 6], 400],
            ['Farhana Rahman', 'Occupational Therapist (visiting)', 'therapist', 'per_session', 'visiting', null, Therapist::where('name', 'Farhana Rahman')->first(), ['basic' => 0], 600],
            ['Md. Hasan', 'Senior Trainer', 'trainer', 'fixed', 'full_time', $user('trainer@cstar.test'), Trainer::where('name', 'Md. Hasan')->first(), ['basic' => 16000, 'house_rent' => 6000, 'conveyance' => 1000], null],
            ['Nasrin Akter', 'Trainer', 'trainer', 'fixed', 'full_time', null, Trainer::where('name', 'Nasrin Akter')->first(), ['basic' => 13000, 'house_rent' => 5000], null],
            ['Rokeya Begum', 'Aya (child care)', 'support', 'fixed', 'full_time', null, null, ['basic' => 9000], null],
            ['Shahidul Islam', 'Security Guard', 'support', 'fixed', 'full_time', null, null, ['basic' => 10000], null],
            ['Moni Akter', 'Cleaner', 'support', 'fixed', 'part_time', null, null, ['basic' => 6000], null],
        ];

        $bank = Account::where('code', '1131')->firstOrFail();
        $guard = null;
        foreach ($staff as [$name, $designation, $department, $payType, $employment, $userId, $profile, $structure, $rate]) {
            $employee = Employee::create([
                'employee_code' => $ids->next('employee', 'EMP', 3), 'name' => $name, 'designation' => $designation, 'department' => $department,
                'branch_id' => $branch->id, 'joining_date' => $joined, 'employment_type' => $employment, 'pay_type' => $payType,
                'payment_method' => in_array($department, ['support'], true) ? 'cash' : ($employment === 'visiting' ? 'bkash' : 'bank'),
                'bank_name' => $department === 'support' || $employment === 'visiting' ? null : 'Dutch-Bangla Bank (demo)',
                'bank_account' => $department === 'support' || $employment === 'visiting' ? null : '101.110.'.random_int(10000, 99999),
                'mfs_number' => $employment === 'visiting' ? '01711000000' : null,
                'user_id' => $userId, 'status' => 'active',
            ]);
            $profile?->update(['employee_id' => $employee->id]);
            $employee->salaryStructures()->create([...$structure, 'effective_from' => $joined, 'created_by' => $accountant->id]);
            if ($rate) {
                $employee->sessionRates()->create(['service_id' => null, 'rate' => $rate, 'effective_from' => $joined]);
            }
            if ($name === 'Shahidul Islam') {
                $guard = $employee;
            }
        }

        // A salary advance, recovered ৳2,000 a month.
        $payroll->giveAdvance($guard, ['date' => today()->subMonth()->startOfMonth()->addDays(9)->toDateString(), 'amount' => 6000, 'installment' => 2000,
            'paid_from_account_id' => $bank->id, 'reason' => 'Family medical expense (demo)'], $accountant);

        // Last month's salary: prepared by the accountant, approved by the branch admin, paid from the bank.
        $run = $payroll->create(['month' => today()->subMonth()->format('Y-m'), 'branch_id' => $branch->id, 'type' => 'salary'], $accountant);
        $payroll->approve($run, $admin);
        $payroll->pay($run->refresh(), $bank->id, $accountant);
    }
}
