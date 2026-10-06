<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Services\BillingSettings;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Package setup, selling a package, active patient packages and billing settings. */
class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_VIEW);

        $packages = Package::with(['service', 'branch'])
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->orderBy('service_id')->orderBy('sessions_count')->get();

        return response()->json(['data' => $packages->map(fn (Package $p) => [
            ...$p->only(['id', 'name', 'name_bn', 'service_id', 'sessions_count', 'validity_days', 'branch_id', 'description', 'is_active']),
            'price' => (float) $p->price,
            'per_session' => round((float) $p->price / $p->sessions_count, 2),
            'service' => $p->service->only(['id', 'name']),
            'branch' => $p->branch?->only(['id', 'name']),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_MANAGE);
        $package = Package::create($this->validated($request));

        return response()->json(['data' => $package], 201);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_MANAGE);
        // Price changes affect only future sales — sold packages keep the price they were sold at.
        $package->update($this->validated($request));

        return response()->json(['data' => $package]);
    }

    /** POST /patients/{id}/packages — sells a package: issues its invoice and starts the package. */
    public function sell(Request $request, Patient $patient, PackageService $packages): JsonResponse
    {
        Gate::authorize(Permission::INVOICES_MANAGE);
        abort_unless(Patient::visibleTo($request->user())->whereKey($patient->id)->exists(), 403);
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'enrollment_id' => ['nullable', 'integer', Rule::exists('enrollments', 'id')->where('patient_id', $patient->id)],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        $invoice = $packages->sell($patient, Package::findOrFail($data['package_id']), $data, $request->user());

        return (new InvoiceResource($invoice->load(['patient', 'branch', 'items', 'allocations.payment'])))->response()->setStatusCode(201);
    }

    /** GET /patient-packages?status=active — sold packages, with sessions left. */
    public function patientPackages(Request $request): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_VIEW);
        $branches = $request->user()->accessibleBranchIds();

        $rows = PatientPackage::with(['patient', 'package', 'service'])
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw("status = 'active' desc")->orderBy('expiry_date')->limit(300)->get();

        return response()->json(['data' => $rows->map(fn (PatientPackage $p) => [
            'id' => $p->id, 'status' => $p->status, 'name' => $p->package->name, 'service' => $p->service->name,
            'patient' => $p->patient->only(['id', 'name', 'patient_code']),
            'total_sessions' => $p->total_sessions, 'used_sessions' => $p->used_sessions, 'remaining' => $p->remaining(),
            'expiry_date' => $p->expiry_date->toDateString(), 'price' => (float) $p->price,
            'renewal_due' => $p->status === 'active' && ($p->remaining() <= 2 || $p->expiry_date->lte(today()->addDays(7))),
        ])]);
    }

    public function settings(BillingSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::PACKAGES_VIEW);

        return response()->json(['data' => $settings->all()]);
    }

    public function updateSettings(Request $request, BillingSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::SETTINGS_MANAGE);
        $data = $request->validate([
            'invoice_due_days' => ['sometimes', 'integer', 'between:0,90'],
            'receptionist_discount_limit_percent' => ['sometimes', 'integer', 'between:0,100'],
            'no_show_deducts_package' => ['sometimes', 'boolean'],
            'late_cancel_deducts_package' => ['sometimes', 'boolean'],
            'admission_fee' => ['nullable', 'numeric', 'min:0'],
            'training_monthly_fee' => ['nullable', 'numeric', 'min:0'],
        ]);
        $settings->update(array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) $v, $data));

        return response()->json(['data' => $settings->all()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('category', 'therapy')],
            'sessions_count' => ['required', 'integer', 'between:1,200'],
            'validity_days' => ['required', 'integer', 'between:1,730'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
    }
}
