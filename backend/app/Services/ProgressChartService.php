<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\GoalProgressEntry;
use App\Models\Patient;
use App\Models\PlanGoal;
use App\Models\TherapySession;
use App\Models\TrainingAttendance;
use App\Models\TrainingRecord;
use Illuminate\Support\Carbon;

/**
 * Progress charts (Sprint 20) — month by month for one child: class attendance rate, average class
 * performance (1–5), therapy sessions held, and the average daily score of every active goal.
 * The same numbers feed the staff profile and the parent portal (family-facing only: no notes).
 */
class ProgressChartService
{
    public function for(Patient $patient, int $months = 6): array
    {
        $start = today()->startOfMonth()->subMonths($months - 1);
        $keys = collect(range(0, $months - 1))->map(fn ($i) => $start->copy()->addMonths($i)->format('Y-m'));
        $byMonth = fn ($rows) => $rows->groupBy(fn ($r) => Carbon::parse($r->date)->format('Y-m'));

        $attendance = $byMonth(TrainingAttendance::where('patient_id', $patient->id)->where('date', '>=', $start)->get(['date', 'status']));
        $records = $byMonth(TrainingRecord::where('patient_id', $patient->id)->where('status', 'final')->whereNotNull('performance')->where('date', '>=', $start)->get(['date', 'performance']));
        $sessions = $byMonth(TherapySession::where('patient_id', $patient->id)->where('status', 'final')->where('date', '>=', $start)->get(['date']));

        $goals = PlanGoal::whereHas('plan', fn ($p) => $p->where('status', 'active')->whereHas('enrollment', fn ($e) => $e->where('patient_id', $patient->id)))
            ->whereNotIn('status', ['discontinued'])->orderBy('id')->get(['id', 'title', 'domain', 'progress_percent', 'status']);
        $scores = GoalProgressEntry::whereIn('plan_goal_id', $goals->pluck('id'))->where('date', '>=', $start)->get(['plan_goal_id', 'date', 'score'])
            ->groupBy('plan_goal_id')->map($byMonth);

        $avg = fn ($rows, string $field) => $rows && $rows->count() ? round($rows->avg($field), 1) : null;

        return [
            'months' => $keys->values(),
            'attendance_rate' => $keys->map(function ($m) use ($attendance) {
                $rows = $attendance->get($m);
                $countable = $rows?->filter(fn ($a) => in_array($a->status, [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Absent], true));

                return $countable && $countable->count() ? (int) round($countable->filter(fn ($a) => $a->status !== AttendanceStatus::Absent)->count() / $countable->count() * 100) : null;
            })->values(),
            'performance' => $keys->map(fn ($m) => $avg($records->get($m), 'performance'))->values(),
            'therapy_sessions' => $keys->map(fn ($m) => $sessions->get($m)?->count() ?? 0)->values(),
            'goals' => $goals->map(fn (PlanGoal $g) => [
                'id' => $g->id, 'title' => $g->title, 'domain' => $g->domain, 'progress_percent' => (int) $g->progress_percent, 'status' => $g->status,
                'scores' => $keys->map(fn ($m) => $avg($scores->get($g->id)?->get($m), 'score'))->values(),
            ])->values(),
        ];
    }
}
