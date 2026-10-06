<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Payment;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\Trainer;
use App\Models\TrainingAttendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        return response()->json(['data' => $stats, 'charts' => $this->charts($user, $branches), 'actions' => $this->actions($user, $branches)]);
    }

    /** Plan §১২ charts — last six months. */
    private function charts(User $user, ?array $branches): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => today()->startOfMonth()->subMonths($i));
        $label = fn (Carbon $m) => $m->format('M');
        $inMonth = fn ($q, string $col, Carbon $m) => $q->whereBetween($col, [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()]);
        $scope = fn ($q, string $column = 'branch_id') => $q->when($branches !== null, fn ($w) => $w->whereIn($column, $branches));
        $charts = [];

        if ($user->can(Permission::PATIENTS_VIEW)) {
            $charts['registrations'] = $months->map(fn ($m) => ['month' => $label($m), 'value' => $inMonth($scope(Patient::query(), 'home_branch_id'), 'registration_date', $m)->count()]);
            $charts['enrollments'] = $months->map(fn ($m) => [
                'month' => $label($m),
                'training' => $inMonth($scope(Enrollment::where('type', EnrollmentType::Training)), 'start_date', $m)->count(),
                'therapy' => $inMonth($scope(Enrollment::where('type', EnrollmentType::Therapy)), 'start_date', $m)->count(),
            ]);
        }
        if ($user->can(Permission::THERAPY_SESSIONS_VIEW) || $user->can(Permission::APPOINTMENTS_VIEW)) {
            $charts['sessions'] = $months->map(fn ($m) => ['month' => $label($m), 'value' => $inMonth($scope(TherapySession::where('status', 'final')), 'date', $m)->count()]);
        }
        if ($user->can(Permission::TRAINING_ATTENDANCE_VIEW) || $user->can(Permission::PATIENTS_VIEW)) {
            $charts['attendance'] = $months->map(function ($m) use ($inMonth, $branches, $label) {
                $rows = $inMonth(TrainingAttendance::query()->when($branches !== null, fn ($q) => $q->whereHas('trainingGroup', fn ($g) => $g->whereIn('branch_id', $branches))), 'date', $m)->pluck('status');
                $present = $rows->filter(fn ($s) => in_array($s->value, ['present', 'late'], true))->count();
                $absent = $rows->filter(fn ($s) => $s->value === 'absent')->count();

                return ['month' => $label($m), 'value' => $present + $absent ? (int) round($present / ($present + $absent) * 100) : null];
            });
        }
        if ($user->can(Permission::REPORTS_FINANCIAL)) {
            $charts['revenue'] = $months->map(fn ($m) => ['month' => $label($m), 'value' => round((float) JournalLine::query()
                ->whereHas('account', fn ($a) => $a->where('type', 'income'))
                ->whereHas('entry', fn ($e) => $e->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()]))
                ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
                ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as v')->value('v'), 2)]);
        }

        return $charts;
    }

    /** Plan §১২ action list — work waiting for this person. */
    private function actions(User $user, ?array $branches): array
    {
        $scope = fn ($q) => $q->when($branches !== null, fn ($w) => $w->whereIn('branch_id', $branches));
        $actions = [];

        if ($user->can(Permission::APPOINTMENT_REQUESTS_MANAGE)) {
            $n = $scope(AppointmentRequest::where('status', 'new'))->count();
            $n && $actions[] = ['kind' => 'requests', 'count' => $n, 'label' => 'New online / portal requests', 'url' => '/app/online-requests'];
        }
        if ($user->can(Permission::APPOINTMENTS_MANAGE)) {
            $n = $scope(Appointment::where('status', AppointmentStatus::Pending->value)->whereDate('date', '>=', today()))->count();
            $n && $actions[] = ['kind' => 'unconfirmed', 'count' => $n, 'label' => 'Appointments not confirmed', 'url' => '/app/appointments'];
        }
        if ($user->can(Permission::APPOINTMENTS_VIEW)) {
            $n = $scope(Appointment::where('type', 'therapy')->whereDate('date', '<', today())->whereDate('date', '>=', today()->subDays(14))
                ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value])
                ->where(fn ($q) => $q->whereDoesntHave('session')->orWhereHas('session', fn ($s) => $s->where('status', 'draft'))))->count();
            $n && $actions[] = ['kind' => 'notes', 'count' => $n, 'label' => 'Session notes not finalized', 'url' => '/app/reports?r=pending-notes'];
        }
        if ($user->can(Permission::INVOICES_MANAGE)) {
            $n = $scope(PatientPackage::where('status', 'active')->where(fn ($q) => $q->whereRaw('total_sessions - used_sessions <= 2')->orWhereDate('expiry_date', '<=', today()->addDays(7))))->count();
            $n && $actions[] = ['kind' => 'packages', 'count' => $n, 'label' => 'Packages to renew', 'url' => '/app/packages?tab=sold'];
        }
        if ($user->can(Permission::INVOICES_VIEW)) {
            $top = $scope(Invoice::open())->selectRaw('patient_id, SUM(due_total) as due')->groupBy('patient_id')->orderByDesc('due')->limit(5)->get();
            $names = Patient::whereIn('id', $top->pluck('patient_id'))->pluck('name', 'id');
            foreach ($top as $row) {
                $actions[] = ['kind' => 'due', 'count' => round((float) $row->due, 2), 'label' => 'Due — '.($names[$row->patient_id] ?? ''), 'url' => "/app/patients/{$row->patient_id}?tab=billing"];
            }
        }

        return $actions;
    }
}
