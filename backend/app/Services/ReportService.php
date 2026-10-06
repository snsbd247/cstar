<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanGoal;
use App\Models\TherapySession;
use App\Models\TrainingAttendance;
use App\Models\TrainingRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Plan §২০ reports. Each report returns {columns, rows, totals, chart} so the screen, the chart,
 * the PDF and the CSV all come from the same numbers. Money reports need reports.financial.
 */
class ReportService
{
    /** key => [group, title, permission, filters] */
    public const CATALOG = [
        'appointments' => ['Operational', 'Appointments by day', Permission::REPORTS_VIEW, ['branch', 'service', 'therapist']],
        'pending-notes' => ['Operational', 'Session notes not finalized', Permission::REPORTS_VIEW, ['branch', 'therapist']],
        'class-attendance' => ['Training', 'Class-wise attendance', Permission::REPORTS_VIEW, ['branch']],
        'trainer-records' => ['Training', 'Trainer-wise training records', Permission::REPORTS_VIEW, ['branch']],
        'therapist-sessions' => ['Therapy', 'Therapist-wise sessions & no-shows', Permission::REPORTS_VIEW, ['branch', 'service']],
        'service-utilization' => ['Therapy', 'Service utilization', Permission::REPORTS_VIEW, ['branch']],
        'goal-achievement' => ['Clinical', 'Goal achievement by programme', Permission::REPORTS_VIEW, ['branch']],
        'registrations' => ['Management', 'Registrations & enrollment trend', Permission::REPORTS_VIEW, ['branch']],
        'revenue' => ['Financial', 'Revenue — training vs therapy', Permission::REPORTS_FINANCIAL, ['branch']],
        'collection' => ['Financial', 'Collection by payment method', Permission::REPORTS_FINANCIAL, ['branch']],
        'due-aging' => ['Financial', 'Due aging (receivables)', Permission::REPORTS_FINANCIAL, ['branch']],
        'branch-performance' => ['Management', 'Branch performance', Permission::REPORTS_FINANCIAL, []],
    ];

    private Carbon $from;

    private Carbon $to;

    /** @var list<int>|null */
    private ?array $branches;

    public function run(string $key, Carbon $from, Carbon $to, ?array $branches, array $filters): array
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
        $this->branches = $branches;
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key))));

        return ['key' => $key, 'title' => self::CATALOG[$key][1], 'from' => $from->toDateString(), 'to' => $to->toDateString(), ...$this->{$method}($filters)];
    }

    private function inBranches($query, string $column = 'branch_id')
    {
        return $query->when($this->branches !== null, fn ($q) => $q->whereIn($column, $this->branches));
    }

    private function col(string $key, string $label, string $type = 'number'): array
    {
        return compact('key', 'label', 'type');
    }

    /** Totals row: sums numeric/money columns. */
    private function totals(Collection $rows, array $columns, string $labelKey): array
    {
        $t = [$labelKey => 'Total'];
        foreach ($columns as $c) {
            if (in_array($c['type'], ['number', 'money'], true) && $c['key'] !== $labelKey) {
                $t[$c['key']] = round($rows->sum($c['key']), 2);
            }
        }

        return $t;
    }

    /** Every month from..to as "Y-m" => "Mon YYYY". */
    private function months(): Collection
    {
        $months = collect();
        for ($m = $this->from->copy()->startOfMonth(); $m->lte($this->to); $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M Y');
        }

        return $months;
    }

    // ---- Operational ------------------------------------------------------------------------------

    private function appointments(array $f): array
    {
        $rows = $this->inBranches(Appointment::query())
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where('status', '!=', AppointmentStatus::Rescheduled->value)
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->when($f['therapist_id'] ?? null, fn ($q, $v) => $q->where('therapist_id', $v))
            ->get()->groupBy(fn ($a) => $a->date->toDateString())->sortKeys()
            ->map(fn ($list, $date) => [
                'date' => $date,
                'total' => $list->count(),
                'completed' => $list->where('status', AppointmentStatus::Completed)->count(),
                'cancelled' => $list->where('status', AppointmentStatus::Cancelled)->count(),
                'no_show' => $list->where('status', AppointmentStatus::NoShow)->count(),
                'upcoming' => $list->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn])->count(),
            ])->values();
        $columns = [$this->col('date', 'Date', 'date'), $this->col('total', 'Appointments'), $this->col('completed', 'Completed'), $this->col('cancelled', 'Cancelled'), $this->col('no_show', 'No-show'), $this->col('upcoming', 'Open')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'date'),
            'chart' => ['type' => 'bar', 'x' => 'date', 'series' => [['key' => 'completed', 'label' => 'Completed'], ['key' => 'no_show', 'label' => 'No-show'], ['key' => 'cancelled', 'label' => 'Cancelled']]]];
    }

    private function pendingNotes(array $f): array
    {
        $rows = $this->inBranches(Appointment::with(['patient', 'service', 'therapist', 'session']))
            ->whereBetween('date', [$this->from->toDateString(), min($this->to, today()->subDay()->endOfDay())->toDateString()])
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value, AppointmentStatus::Completed->value])
            ->where('type', 'therapy')
            ->when($f['therapist_id'] ?? null, fn ($q, $v) => $q->where('therapist_id', $v))
            ->orderBy('date')->get()
            ->filter(fn ($a) => $a->session?->status !== 'final')
            ->map(fn ($a) => [
                'date' => $a->date->toDateString(), 'therapist' => $a->therapist->name, 'child' => $a->patient->name,
                'service' => $a->service->name, 'note' => $a->session ? 'Draft' : 'Not started', 'days' => (int) $a->date->diffInDays(today()),
            ])->values();

        return ['columns' => [$this->col('date', 'Date', 'date'), $this->col('therapist', 'Therapist', 'text'), $this->col('child', 'Child', 'text'),
            $this->col('service', 'Service', 'text'), $this->col('note', 'Note', 'text'), $this->col('days', 'Days waiting')], 'rows' => $rows, 'totals' => null, 'chart' => null];
    }

    // ---- Training ---------------------------------------------------------------------------------

    private function classAttendance(array $f): array
    {
        $rows = TrainingAttendance::with('trainingGroup')
            ->when($this->branches !== null, fn ($q) => $q->whereHas('trainingGroup', fn ($g) => $g->whereIn('branch_id', $this->branches)))
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get()->groupBy('training_group_id')
            ->map(function ($list) {
                $present = $list->filter(fn ($a) => in_array($a->status->value, ['present', 'late'], true))->count();
                $absent = $list->filter(fn ($a) => $a->status->value === 'absent')->count();

                return [
                    'class' => $list->first()->trainingGroup->name,
                    'students' => $list->pluck('enrollment_id')->unique()->count(),
                    'present' => $present, 'absent' => $absent,
                    'leave' => $list->filter(fn ($a) => $a->status->value === 'leave')->count(),
                    'rate' => $present + $absent ? round($present / ($present + $absent) * 100) : null,
                ];
            })->sortBy('class')->values();

        return ['columns' => [$this->col('class', 'Class', 'text'), $this->col('students', 'Students'), $this->col('present', 'Present + late'),
            $this->col('absent', 'Absent'), $this->col('leave', 'Leave'), $this->col('rate', 'Attendance %', 'percent')],
            'rows' => $rows, 'totals' => null, 'chart' => ['type' => 'bar', 'x' => 'class', 'series' => [['key' => 'rate', 'label' => 'Attendance %']]]];
    }

    private function trainerRecords(array $f): array
    {
        $rows = TrainingRecord::with(['trainer', 'enrollment'])
            ->when($this->branches !== null, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->whereIn('branch_id', $this->branches)))
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get()->groupBy('trainer_id')
            ->map(fn ($list) => [
                'trainer' => $list->first()->trainer?->name ?? '—',
                'records' => $list->count(),
                'final' => $list->where('status', 'final')->count(),
                'children' => $list->pluck('patient_id')->unique()->count(),
                'avg_performance' => round((float) $list->avg('performance'), 1),
            ])->sortBy('trainer')->values();
        $columns = [$this->col('trainer', 'Trainer', 'text'), $this->col('records', 'Records'), $this->col('final', 'Finalized'), $this->col('children', 'Children'), $this->col('avg_performance', 'Avg rating (1–5)', 'decimal')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => null, 'chart' => ['type' => 'bar', 'x' => 'trainer', 'series' => [['key' => 'records', 'label' => 'Records']]]];
    }

    // ---- Therapy ----------------------------------------------------------------------------------

    private function therapistSessions(array $f): array
    {
        $rows = $this->inBranches(Appointment::with('therapist'))
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where('status', '!=', AppointmentStatus::Rescheduled->value)
            ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->get()->groupBy('therapist_id')
            ->map(function ($list) {
                $noShow = $list->where('status', AppointmentStatus::NoShow)->count();
                $held = $list->where('status', AppointmentStatus::Completed)->count();

                return [
                    'therapist' => $list->first()->therapist->name,
                    'appointments' => $list->count(),
                    'sessions' => TherapySession::whereIn('appointment_id', $list->pluck('id'))->where('status', 'final')->count(),
                    'no_show' => $noShow,
                    'cancelled' => $list->where('status', AppointmentStatus::Cancelled)->count(),
                    'no_show_rate' => $held + $noShow ? round($noShow / ($held + $noShow) * 100) : null,
                ];
            })->sortBy('therapist')->values();
        $columns = [$this->col('therapist', 'Therapist', 'text'), $this->col('appointments', 'Appointments'), $this->col('sessions', 'Sessions (final notes)'),
            $this->col('no_show', 'No-show'), $this->col('cancelled', 'Cancelled'), $this->col('no_show_rate', 'No-show %', 'percent')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, array_slice($columns, 0, 5), 'therapist'),
            'chart' => ['type' => 'bar', 'x' => 'therapist', 'series' => [['key' => 'sessions', 'label' => 'Sessions'], ['key' => 'no_show', 'label' => 'No-show']]]];
    }

    private function serviceUtilization(array $f): array
    {
        $income = $this->incomeByService();
        $rows = $this->inBranches(Appointment::with('service'))
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where('status', '!=', AppointmentStatus::Rescheduled->value)
            ->get()->groupBy('service_id')
            ->map(fn ($list, $serviceId) => [
                'service' => $list->first()->service->name,
                'appointments' => $list->count(),
                'completed' => $list->where('status', AppointmentStatus::Completed)->count(),
                'children' => $list->pluck('patient_id')->unique()->count(),
                'no_show' => $list->where('status', AppointmentStatus::NoShow)->count(),
                'income' => $income[$serviceId] ?? 0,
            ])->sortByDesc('appointments')->values();
        $columns = [$this->col('service', 'Service', 'text'), $this->col('appointments', 'Appointments'), $this->col('completed', 'Completed'),
            $this->col('children', 'Children'), $this->col('no_show', 'No-show'), $this->col('income', 'Income', 'money')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'service'),
            'chart' => ['type' => 'bar', 'x' => 'service', 'series' => [['key' => 'completed', 'label' => 'Completed']]]];
    }

    // ---- Clinical ---------------------------------------------------------------------------------

    private function goalAchievement(array $f): array
    {
        $goals = PlanGoal::with(['plan.enrollment.therapyEnrollment.service'])
            ->whereHas('plan', fn ($p) => $p->where('status', 'active')
                ->whereHas('enrollment', fn ($e) => $e->whereIn('status', EnrollmentStatus::current())->when($this->branches !== null, fn ($q) => $q->whereIn('branch_id', $this->branches))))
            ->where('status', '!=', 'discontinued')->get();

        $rows = $goals->groupBy(fn ($g) => $g->plan->enrollment->type === EnrollmentType::Training ? 'Regular Training' : ($g->plan->enrollment->therapyEnrollment?->service?->name ?? 'Therapy'))
            ->map(fn ($list, $programme) => [
                'programme' => $programme,
                'children' => $list->pluck('plan.patient_id')->unique()->count(),
                'goals' => $list->count(),
                'achieved' => $list->where('status', 'achieved')->count(),
                'in_progress' => $list->where('status', 'in_progress')->count(),
                'avg_progress' => (int) round((float) $list->avg('progress_percent')),
            ])->sortBy('programme')->values();
        $columns = [$this->col('programme', 'Programme', 'text'), $this->col('children', 'Children'), $this->col('goals', 'Goals'),
            $this->col('achieved', 'Achieved'), $this->col('in_progress', 'In progress'), $this->col('avg_progress', 'Avg progress', 'percent')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => null, 'chart' => ['type' => 'bar', 'x' => 'programme', 'series' => [['key' => 'avg_progress', 'label' => 'Avg progress %']]],
            'note' => 'Current active plans (not limited to the date range).'];
    }

    // ---- Management -------------------------------------------------------------------------------

    private function registrations(array $f): array
    {
        $patients = $this->inBranches(Patient::query(), 'home_branch_id')
            ->whereBetween('registration_date', [$this->from->toDateString(), $this->to->toDateString()])->get(['id', 'registration_date']);
        $enrollments = $this->inBranches(Enrollment::query())->whereBetween('start_date', [$this->from->toDateString(), $this->to->toDateString()])->get(['id', 'type', 'start_date']);

        $rows = $this->months()->map(fn ($label, $ym) => [
            'month' => $label,
            'registrations' => $patients->filter(fn ($p) => $p->registration_date?->format('Y-m') === $ym)->count(),
            'training' => $enrollments->filter(fn ($e) => $e->type === EnrollmentType::Training && $e->start_date->format('Y-m') === $ym)->count(),
            'therapy' => $enrollments->filter(fn ($e) => $e->type === EnrollmentType::Therapy && $e->start_date->format('Y-m') === $ym)->count(),
        ])->values();
        $columns = [$this->col('month', 'Month', 'text'), $this->col('registrations', 'New children'), $this->col('training', 'New training enrollments'), $this->col('therapy', 'New therapy enrollments')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'month'),
            'chart' => ['type' => 'bar', 'x' => 'month', 'series' => [['key' => 'registrations', 'label' => 'New children'], ['key' => 'training', 'label' => 'Training'], ['key' => 'therapy', 'label' => 'Therapy']]]];
    }

    private function branchPerformance(array $f): array
    {
        $rows = Branch::when($this->branches !== null, fn ($q) => $q->whereIn('id', $this->branches))->orderBy('name')->get()
            ->map(function (Branch $b) {
                $income = $this->incomeLines([$b->id]);

                return [
                    'branch' => $b->name,
                    'children' => Patient::where('home_branch_id', $b->id)->where('status', 'active')->count(),
                    'new' => Patient::where('home_branch_id', $b->id)->whereBetween('registration_date', [$this->from->toDateString(), $this->to->toDateString()])->count(),
                    'revenue' => round($income->sum(fn ($l) => (float) $l->credit - (float) $l->debit), 2),
                    'collection' => round((float) Payment::where('branch_id', $b->id)->where('status', 'completed')->where('type', 'payment')->whereBetween('paid_at', [$this->from, $this->to])->sum('amount'), 2),
                    'due' => round((float) Invoice::where('branch_id', $b->id)->open()->sum('due_total'), 2),
                ];
            });
        $columns = [$this->col('branch', 'Branch', 'text'), $this->col('children', 'Active children'), $this->col('new', 'New'), $this->col('revenue', 'Revenue', 'money'),
            $this->col('collection', 'Collection', 'money'), $this->col('due', 'Due now', 'money')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'branch'),
            'chart' => ['type' => 'bar', 'x' => 'branch', 'series' => [['key' => 'revenue', 'label' => 'Revenue'], ['key' => 'collection', 'label' => 'Collection']]]];
    }

    // ---- Financial --------------------------------------------------------------------------------

    private function incomeLines(?array $branches = null): Collection
    {
        $branches ??= $this->branches;

        return JournalLine::with('account')
            ->whereHas('account', fn ($a) => $a->where('type', 'income'))
            ->whereHas('entry', fn ($e) => $e->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]))
            ->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->with('entry:id,date')->get();
    }

    /** @return array<int, float> service id => income */
    private function incomeByService(): array
    {
        return $this->incomeLines()->whereNotNull('service_id')->groupBy('service_id')
            ->map(fn ($lines) => round($lines->sum(fn ($l) => (float) $l->credit - (float) $l->debit), 2))->all();
    }

    private function revenue(array $f): array
    {
        $bucket = function (Account $a): string {
            return match (true) {
                $a->system_key === 'income_training' => 'training',
                $a->service_id !== null || $a->system_key === 'income_therapy_other' => 'therapy',
                $a->system_key === 'income_assessment' => 'assessment',
                $a->system_key === 'discount_allowed' => 'discount',
                default => 'other',
            };
        };
        $lines = $this->incomeLines();
        $rows = $this->months()->map(function ($label, $ym) use ($lines, $bucket) {
            $month = $lines->filter(fn ($l) => $l->entry->date->format('Y-m') === $ym);
            $sum = fn (string $b) => round($month->filter(fn ($l) => $bucket($l->account) === $b)->sum(fn ($l) => (float) $l->credit - (float) $l->debit), 2);
            $row = ['month' => $label, 'training' => $sum('training'), 'therapy' => $sum('therapy'), 'assessment' => $sum('assessment'), 'other' => $sum('other'), 'discount' => $sum('discount')];
            $row['net'] = round($row['training'] + $row['therapy'] + $row['assessment'] + $row['other'] + $row['discount'], 2);

            return $row;
        })->values();
        $columns = [$this->col('month', 'Month', 'text'), $this->col('training', 'Training', 'money'), $this->col('therapy', 'Therapy', 'money'),
            $this->col('assessment', 'Assessment', 'money'), $this->col('other', 'Admission & other', 'money'), $this->col('discount', 'Discounts', 'money'), $this->col('net', 'Net revenue', 'money')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'month'),
            'chart' => ['type' => 'bar', 'x' => 'month', 'series' => [['key' => 'training', 'label' => 'Training'], ['key' => 'therapy', 'label' => 'Therapy']]],
            'note' => 'Income as earned in the books: package money counts when sessions happen.'];
    }

    private function collection(array $f): array
    {
        $methods = Payment::METHODS;
        $rows = $this->inBranches(Payment::query())->where('status', 'completed')->whereBetween('paid_at', [$this->from, $this->to])->get()
            ->groupBy(fn ($p) => $p->paid_at->toDateString())->sortKeys()
            ->map(function ($list, $date) use ($methods) {
                $row = ['date' => $date];
                foreach ($methods as $m) {
                    $row[$m] = round($list->where('method', $m)->sum(fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount), 2);
                }
                $row['total'] = round(array_sum(array_intersect_key($row, array_flip($methods))), 2);

                return $row;
            })->values();
        $columns = [$this->col('date', 'Date', 'date'), $this->col('cash', 'Cash', 'money'), $this->col('bkash', 'bKash', 'money'), $this->col('nagad', 'Nagad', 'money'),
            $this->col('bank', 'Bank', 'money'), $this->col('card', 'Card', 'money'), $this->col('total', 'Total', 'money')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'date'),
            'chart' => ['type' => 'bar', 'x' => 'date', 'series' => [['key' => 'total', 'label' => 'Collected']]]];
    }

    private function dueAging(array $f): array
    {
        $buckets = ['not_due' => 'Not yet due', 'd30' => '1–30 days', 'd60' => '31–60', 'd90' => '61–90', 'd90p' => '90+'];
        $rows = $this->inBranches(Invoice::with('patient'))->open()->get()->groupBy('patient_id')
            ->map(function ($list) use ($buckets) {
                $row = ['child' => $list->first()->patient->name, 'phone' => $list->first()->patient->phone] + array_fill_keys(array_keys($buckets), 0.0);
                foreach ($list as $inv) {
                    $late = $inv->due_date && $inv->due_date->isPast() ? (int) $inv->due_date->diffInDays(today()) : 0;
                    $key = $late === 0 ? 'not_due' : ($late <= 30 ? 'd30' : ($late <= 60 ? 'd60' : ($late <= 90 ? 'd90' : 'd90p')));
                    $row[$key] = round($row[$key] + (float) $inv->due_total, 2);
                }
                $row['total'] = round(array_sum(array_intersect_key($row, $buckets)), 2);

                return $row;
            })->sortByDesc('total')->values();
        $columns = [$this->col('child', 'Child', 'text'), $this->col('phone', 'Phone', 'text'),
            ...collect($buckets)->map(fn ($l, $k) => $this->col($k, $l, 'money'))->values()->all(), $this->col('total', 'Total due', 'money')];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns, 'child'), 'chart' => null,
            'note' => 'Due now, by how long each invoice is past its due date (date range not applied).'];
    }
}
