<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use App\Services\PayrollService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Accounts B — employees, pay setup, advances, payroll runs and payslips (Accounts §৭). */
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    // ---- Employees --------------------------------------------------------------------------------

    public function employees(Request $request): JsonResponse
    {
        $this->authorizeView();
        $branches = $request->user()->accessibleBranchIds();

        $rows = Employee::with(['branch', 'user', 'therapist', 'trainer', 'salaryStructures', 'advances' => fn ($q) => $q->where('status', 'active')])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->where('status', '!=', 'left'))
            ->orderBy('department')->orderBy('name')->get();

        return response()->json(['data' => $rows->map(fn (Employee $e) => $this->presentEmployee($e))]);
    }

    public function employee(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeView();
        abort_unless($request->user()->canAccessBranch($employee->branch_id), 403);
        $employee->load(['branch', 'user', 'therapist', 'trainer', 'salaryStructures', 'sessionRates.service', 'advances.paidFrom']);

        return response()->json(['data' => [
            ...$this->presentEmployee($employee),
            'structures' => $employee->salaryStructures->map(fn ($s) => [
                ...$s->only(['id', 'other_allowances', 'included_sessions']),
                'effective_from' => $s->effective_from->toDateString(),
                'basic' => (float) $s->basic, 'house_rent' => (float) $s->house_rent, 'medical' => (float) $s->medical, 'conveyance' => (float) $s->conveyance,
                'revenue_share_percent' => $s->revenue_share_percent !== null ? (float) $s->revenue_share_percent : null,
                'total' => $s->monthlyTotal(),
            ]),
            'rates' => $employee->sessionRates->map(fn ($r) => ['id' => $r->id, 'service' => $r->service?->only(['id', 'name']), 'rate' => (float) $r->rate, 'effective_from' => $r->effective_from->toDateString()]),
            'advances' => $employee->advances->sortByDesc('date')->values()->map(fn ($a) => [
                'id' => $a->id, 'date' => $a->date->toDateString(), 'amount' => (float) $a->amount, 'installment' => (float) $a->installment,
                'balance' => (float) $a->balance, 'status' => $a->status, 'reason' => $a->reason, 'paid_from' => $a->paidFrom->name,
            ]),
            'payslips' => PayrollItem::with('run')->where('employee_id', $employee->id)->whereHas('run', fn ($q) => $q->where('status', '!=', 'draft'))
                ->latest('id')->limit(24)->get()->map(fn ($i) => $this->presentSlip($i)),
        ]]);
    }

    /** GET /hr/employee-options — profiles and logins that can be linked to an employee. */
    public function employeeOptions(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $branches = $request->user()->accessibleBranchIds();

        return response()->json(['data' => [
            'therapists' => Therapist::when($branches !== null, fn ($q) => $q->whereIn('primary_branch_id', $branches))->orderBy('name')->get(['id', 'name', 'employee_id']),
            'trainers' => Trainer::when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->orderBy('name')->get(['id', 'name', 'employee_id']),
            'users' => User::where('user_type', 'staff')
                ->when($branches !== null, fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branches)))
                ->orderBy('name')->get(['id', 'name', 'email']),
            'services' => Service::where('category', 'therapy')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]]);
    }

    public function storeEmployee(Request $request, IdGenerator $ids): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $data = $this->validatedEmployee($request);

        $employee = DB::transaction(function () use ($data, $ids) {
            $employee = Employee::create([...collect($data)->except(['therapist_id', 'trainer_id'])->all(), 'employee_code' => $this->nextCode($ids)]);
            $this->link($employee, $data);

            return $employee;
        });

        return response()->json(['data' => $this->presentEmployee($employee->load(['branch', 'user', 'therapist', 'trainer', 'salaryStructures', 'advances']))], 201);
    }

    public function updateEmployee(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $data = $this->validatedEmployee($request, $employee);
        DB::transaction(function () use ($employee, $data) {
            $employee->update(collect($data)->except(['therapist_id', 'trainer_id'])->all());
            $this->link($employee, $data);
        });

        return response()->json(['data' => $this->presentEmployee($employee->refresh()->load(['branch', 'user', 'therapist', 'trainer', 'salaryStructures', 'advances']))]);
    }

    /** A raise or new allowance is a new structure from a date — earlier payslips keep the old one. */
    public function saveStructure(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'basic' => ['required', 'numeric', 'min:0'],
            'house_rent' => ['nullable', 'numeric', 'min:0'],
            'medical' => ['nullable', 'numeric', 'min:0'],
            'conveyance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'array', 'max:10'],
            'other_allowances.*.name' => ['required', 'string', 'max:60'],
            'other_allowances.*.amount' => ['required', 'numeric', 'min:0'],
            'included_sessions' => ['nullable', 'integer', 'min:0', 'max:500'],
            'revenue_share_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $structure = $employee->salaryStructures()->updateOrCreate(['effective_from' => $data['effective_from']], [
            ...$data, 'house_rent' => $data['house_rent'] ?? 0, 'medical' => $data['medical'] ?? 0, 'conveyance' => $data['conveyance'] ?? 0,
            'included_sessions' => $data['included_sessions'] ?? 0, 'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $structure], 201);
    }

    public function saveRate(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('category', 'therapy')],
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        return response()->json(['data' => $employee->sessionRates()->updateOrCreate(
            ['service_id' => $data['service_id'] ?? null, 'effective_from' => $data['effective_from']], ['rate' => $data['rate']],
        )], 201);
    }

    public function giveAdvance(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        abort_unless($request->user()->canAccessBranch($employee->branch_id), 403);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1'],
            'installment' => ['nullable', 'numeric', 'min:1', 'lte:amount'],
            'paid_from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $this->payroll->giveAdvance($employee, $data, $request->user())], 201);
    }

    // ---- Payroll runs -----------------------------------------------------------------------------

    public function runs(Request $request): JsonResponse
    {
        $this->authorizeView();
        $branches = $request->user()->accessibleBranchIds();

        return response()->json(['data' => PayrollRun::with(['branch', 'preparer', 'approver'])->withCount('items')
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->latest('month')->latest('id')->limit(60)->get()
            ->map(fn (PayrollRun $r) => $this->presentRun($r, $request))]);
    }

    public function storeRun(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type' => ['required', Rule::in(['salary', 'bonus'])],
            'title' => ['nullable', 'required_if:type,bonus', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);
        $run = $this->payroll->create($data, $request->user());

        return response()->json(['data' => $this->presentRun($run->loadCount('items')->load(['branch', 'preparer', 'approver']), $request)], 201);
    }

    public function run(Request $request, PayrollRun $run): JsonResponse
    {
        $this->authorizeView();
        abort_unless($request->user()->canAccessBranch($run->branch_id), 403);
        $run->load(['branch', 'preparer', 'approver', 'items.employee'])->loadCount('items');

        return response()->json(['data' => [
            ...$this->presentRun($run, $request),
            'items' => $run->items->sortBy(fn ($i) => [$i->department, $i->employee->name])->values()->map(fn (PayrollItem $i) => [
                ...$i->only(['id', 'department', 'session_count', 'breakdown', 'note']),
                ...collect(['fixed_amount', 'session_pay', 'bonus', 'other_addition', 'absence_deduction', 'gross', 'advance_deduction', 'tax', 'other_deduction', 'net_pay', 'paid_amount'])
                    ->mapWithKeys(fn ($k) => [$k => (float) $i->{$k}])->all(),
                'paid_at' => $i->paid_at,
                'employee' => $i->employee->only(['id', 'employee_code', 'name', 'designation', 'pay_type', 'payment_method', 'bank_account', 'mfs_number']),
            ]),
        ]]);
    }

    /** POST /payroll/runs/{id}/{action} — recalculate | approve | reopen | pay */
    public function action(Request $request, PayrollRun $run, string $action): JsonResponse
    {
        abort_unless($request->user()->canAccessBranch($run->branch_id), 403);
        $user = $request->user();
        match ($action) {
            'recalculate' => Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE) && $this->payroll->calculate($run),
            'approve' => Gate::authorize(Permission::ACCOUNTS_PAYROLL_APPROVE) && $this->payroll->approve($run, $user),
            'reopen' => Gate::authorize(Permission::ACCOUNTS_PAYROLL_APPROVE) && $this->payroll->reverse($run, $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason']),
            'pay' => Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE) && $this->payroll->pay($run, (int) $request->validate([
                'paid_from_account_id' => ['required', 'integer', 'exists:accounts,id'],
                'item_ids' => ['nullable', 'array'], 'item_ids.*' => ['integer'],
            ])['paid_from_account_id'], $user, $request->input('item_ids')),
        };

        return $this->run($request, $run->refresh());
    }

    public function updateItem(Request $request, PayrollItem $item): JsonResponse
    {
        Gate::authorize(Permission::ACCOUNTS_PAYROLL_MANAGE);
        abort_unless($request->user()->canAccessBranch($item->run->branch_id), 403);
        $data = $request->validate([
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'other_addition' => ['nullable', 'numeric', 'min:0'],
            'absence_deduction' => ['nullable', 'numeric', 'min:0'],
            'advance_deduction' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $this->payroll->updateItem($item, $data)]);
    }

    public function salarySheet(Request $request, PayrollRun $run, PdfService $pdf): Response
    {
        $this->authorizeView();
        abort_unless($request->user()->canAccessBranch($run->branch_id), 403);
        AuditLogger::log('exported', $run, new: ['format' => 'pdf']);

        return $pdf->response('pdf.salary-sheet', ['run' => $run->load(['branch', 'items.employee', 'approver', 'preparer'])], 'Salary Sheet', "{$run->run_no}.pdf");
    }

    /** Payslip — accounts staff, or the employee themself (Accounts §১৪ "নিজের payslip"). */
    public function payslip(Request $request, PayrollItem $item, PdfService $pdf): Response
    {
        $item->load(['run.branch', 'employee']);
        $own = $item->employee->user_id === $request->user()->id;
        abort_unless($own || ($request->user()->can(Permission::ACCOUNTS_PAYROLL_MANAGE) || $request->user()->can(Permission::ACCOUNTS_PAYROLL_APPROVE)) && $request->user()->canAccessBranch($item->run->branch_id), 403);
        abort_if($item->run->status === 'draft', 404, 'This payslip is not final yet.');

        return $pdf->response('pdf.payslip', ['item' => $item], 'Payslip', "payslip-{$item->employee->employee_code}-{$item->run->month}.pdf");
    }

    /** GET /me/payslips — any staff member's own final payslips. */
    public function myPayslips(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();
        if (! $employee) {
            return response()->json(['data' => [], 'employee' => null]);
        }

        return response()->json([
            'employee' => $employee->only(['employee_code', 'name', 'designation']),
            'data' => PayrollItem::with('run')->where('employee_id', $employee->id)->whereHas('run', fn ($q) => $q->where('status', '!=', 'draft'))
                ->latest('id')->limit(24)->get()->map(fn ($i) => $this->presentSlip($i)),
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    private function presentEmployee(Employee $e): array
    {
        $current = $e->salaryStructures->first(fn ($s) => $s->effective_from->lte(today()));

        return [
            ...$e->only(['id', 'employee_code', 'name', 'designation', 'department', 'employment_type', 'pay_type', 'phone', 'payment_method', 'bank_name', 'bank_account', 'mfs_number', 'status', 'notes', 'branch_id', 'user_id']),
            'joining_date' => $e->joining_date->toDateString(),
            'left_date' => $e->left_date?->toDateString(),
            'branch' => $e->branch?->only(['id', 'name']),
            'user' => $e->user?->only(['id', 'name', 'email']),
            'therapist' => $e->therapist?->only(['id', 'name']),
            'trainer' => $e->trainer?->only(['id', 'name']),
            'monthly_salary' => $current?->monthlyTotal() ?? 0,
            'advance_balance' => round((float) $e->advances->where('status', 'active')->sum('balance'), 2),
        ];
    }

    private function presentRun(PayrollRun $r, Request $request): array
    {
        $user = $request->user();

        return [
            ...$r->only(['id', 'run_no', 'month', 'type', 'title', 'status', 'notes']),
            'label' => $r->label(),
            'total_gross' => (float) $r->total_gross, 'total_deductions' => (float) $r->total_deductions, 'total_net' => (float) $r->total_net,
            'items_count' => $r->items_count ?? null,
            'branch' => $r->branch?->only(['id', 'name']),
            'prepared_by' => $r->preparer?->only(['id', 'name']),
            'approved_by' => $r->approver?->only(['id', 'name']),
            'approved_at' => $r->approved_at,
            'can' => [
                'edit' => $r->status === 'draft' && $user->can(Permission::ACCOUNTS_PAYROLL_MANAGE),
                'approve' => $r->status === 'draft' && $user->can(Permission::ACCOUNTS_PAYROLL_APPROVE) && ($r->prepared_by !== $user->id || $user->isSuperAdmin()),
                'reopen' => $r->status === 'posted' && $user->can(Permission::ACCOUNTS_PAYROLL_APPROVE),
                'pay' => in_array($r->status, ['posted'], true) && $user->can(Permission::ACCOUNTS_PAYROLL_MANAGE),
            ],
        ];
    }

    private function presentSlip(PayrollItem $i): array
    {
        return [
            'id' => $i->id, 'run_no' => $i->run->run_no, 'label' => $i->run->label(), 'month' => $i->run->month, 'status' => $i->run->status,
            'gross' => (float) $i->gross, 'net_pay' => (float) $i->net_pay, 'paid_at' => $i->paid_at,
        ];
    }

    private function validatedEmployee(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['required', Rule::in(Employee::DEPARTMENTS)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'joining_date' => ['required', 'date'],
            'left_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'visiting'])],
            'pay_type' => ['required', Rule::in(Employee::PAY_TYPES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'bkash', 'nagad'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'mfs_number' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'therapist_id' => ['nullable', 'integer', 'exists:therapists,id', 'prohibited_unless:department,therapist'],
            'trainer_id' => ['nullable', 'integer', 'exists:trainers,id', 'prohibited_unless:department,trainer'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'left'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['therapist_id.prohibited_unless' => 'Only a therapist employee links to a therapist profile.', 'trainer_id.prohibited_unless' => 'Only a trainer employee links to a trainer profile.']);
    }

    /** TRAINER ≠ THERAPIST: an employee links to at most one of the two profiles. */
    private function link(Employee $employee, array $data): void
    {
        Therapist::where('employee_id', $employee->id)->update(['employee_id' => null]);
        Trainer::where('employee_id', $employee->id)->update(['employee_id' => null]);
        if (! empty($data['therapist_id'])) {
            Therapist::whereKey($data['therapist_id'])->update(['employee_id' => $employee->id]);
        }
        if (! empty($data['trainer_id'])) {
            Trainer::whereKey($data['trainer_id'])->update(['employee_id' => $employee->id]);
        }
    }

    private function nextCode(IdGenerator $ids): string
    {
        // EMP-2026-001 — the year of hiring stays in the code.
        return $ids->next('employee', 'EMP', 3);
    }

    private function authorizeView(): void
    {
        abort_unless(request()->user()->can(Permission::ACCOUNTS_PAYROLL_MANAGE) || request()->user()->can(Permission::ACCOUNTS_PAYROLL_APPROVE), 403);
    }
}
