<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Diagnosis;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Dropdown data for forms. */
class LookupController extends Controller
{
    /** Everything the "New enrollment" form needs for one branch. */
    public function enrollmentOptions(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ENROLLMENTS_MANAGE);
        $request->validate(['branch_id' => ['required', 'integer']]);
        $branchId = $request->integer('branch_id');
        abort_unless($request->user()->canAccessBranch($branchId), 403);

        $groups = TrainingGroup::with('leadTrainer:id,name')
            ->where('branch_id', $branchId)->where('status', 'active')
            ->withCount(['trainingEnrollments as occupied' => fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->whereIn('status', EnrollmentStatus::open()))])
            ->orderBy('name')->get();

        return response()->json(['data' => [
            'classes' => $groups->map(fn (TrainingGroup $g) => [
                'id' => $g->id, 'code' => $g->code, 'name' => $g->name,
                'lead_trainer' => $g->leadTrainer ? ['id' => $g->leadTrainer->id, 'name' => $g->leadTrainer->name] : null,
                'max_students' => $g->max_students, 'occupied' => $g->occupied,
            ]),
            'trainers' => Trainer::where('branch_id', $branchId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => Service::therapy()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'default_duration_min']),
            'therapists' => Therapist::with('services:id')->where('status', 'active')->orderBy('name')->get()
                ->map(fn (Therapist $t) => [
                    'id' => $t->id, 'name' => $t->name, 'type' => $t->therapist_type->label(),
                    'service_ids' => $t->services->pluck('id'),
                ]),
        ]]);
    }

    /** Services that can be booked as appointments (therapy, assessment, consultation — never training). */
    public function bookableServices(Request $request): JsonResponse
    {
        // Appointment booking and the package form (accountants set up packages too).
        abort_unless($request->user()->can(Permission::APPOINTMENTS_VIEW) || $request->user()->can(Permission::PACKAGES_MANAGE), 403);

        return response()->json(['data' => Service::where('is_active', true)->where('category', '!=', 'training')
            ->orderBy('sort_order')->get(['id', 'name', 'category', 'default_duration_min', 'default_price'])]);
    }

    public function diagnoses(): JsonResponse
    {
        Gate::authorize(Permission::PATIENTS_VIEW);

        return response()->json(['data' => Diagnosis::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_bn'])]);
    }
}
