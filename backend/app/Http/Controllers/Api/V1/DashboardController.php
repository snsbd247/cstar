<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Therapist;
use App\Models\Trainer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plan §১২ admin dashboard cards — each number is limited to the user's branches and permissions. */
class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $branches = $user->accessibleBranchIds();
        $scope = fn ($q, string $column = 'branch_id') => $q->when($branches !== null, fn ($w) => $w->whereIn($column, $branches));
        $current = EnrollmentStatus::current();

        $patients = Patient::visibleTo($user)->where('status', 'active');
        $hasOpen = fn (EnrollmentType $type) => fn ($q) => $q->where('type', $type)->whereIn('status', $current);

        $stats = [];
        if ($user->can(Permission::PATIENTS_VIEW)) {
            $stats['children'] = (clone $patients)->count();
            $stats['students'] = (clone $patients)->whereHas('enrollments', $hasOpen(EnrollmentType::Training))->whereDoesntHave('enrollments', $hasOpen(EnrollmentType::Therapy))->count();
            $stats['therapy_only'] = (clone $patients)->whereHas('enrollments', $hasOpen(EnrollmentType::Therapy))->whereDoesntHave('enrollments', $hasOpen(EnrollmentType::Training))->count();
            $stats['both'] = (clone $patients)->whereHas('enrollments', $hasOpen(EnrollmentType::Training))->whereHas('enrollments', $hasOpen(EnrollmentType::Therapy))->count();
        }
        if ($user->can(Permission::TRAINERS_VIEW)) {
            $stats['trainers'] = $scope(Trainer::where('status', 'active'))->count();
        }
        if ($user->can(Permission::THERAPISTS_VIEW)) {
            $stats['therapists'] = $scope(Therapist::where('status', 'active'), 'primary_branch_id')->count();
        }
        if ($user->can(Permission::APPOINTMENTS_VIEW)) {
            $stats['appointments_today'] = $scope(Appointment::whereDate('date', today())->whereIn('status', AppointmentStatus::live()))->count();
        }
        if ($user->can(Permission::PAYMENTS_VIEW)) {
            $today = $scope(Payment::where('status', 'completed')->whereDate('paid_at', today()));
            $stats['collection_today'] = round((float) (clone $today)->where('type', 'payment')->sum('amount') - (float) (clone $today)->where('type', 'refund')->sum('amount'), 2);
        }
        if ($user->can(Permission::INVOICES_VIEW)) {
            $stats['total_due'] = round((float) $scope(Invoice::open())->sum('due_total'), 2);
        }

        return response()->json(['data' => $stats]);
    }
}
