<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regular-student attendance (Plan §১২, §১৭).
 *
 *  Rate = (present + late) ÷ (present + late + absent) × 100 — leave and holidays don't count against the child.
 *  Trainers may mark or correct the last TRAINER_EDIT_DAYS days; office staff any past date. Never the future.
 */
class AttendanceService
{
    public const TRAINER_EDIT_DAYS = 3;

    /** Today's roster with any attendance already marked. */
    public function roster(TrainingGroup $group, Carbon $date): array
    {
        $enrollments = $group->rosterOn($date)->with('patient')->get();
        $marked = TrainingAttendance::where('training_group_id', $group->id)->whereDate('date', $date)->get()->keyBy('enrollment_id');

        return [
            'class' => ['id' => $group->id, 'name' => $group->name, 'code' => $group->code],
            'date' => $date->toDateString(),
            'is_holiday' => $group->isHoliday($date),
            'is_class_day' => $group->scheduleFor($date) !== null,
            'students' => $enrollments->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'patient' => ['id' => $e->patient->id, 'name' => $e->patient->name, 'patient_code' => $e->patient->patient_code, 'has_photo' => $e->patient->photo_path !== null],
                'status' => $marked->get($e->id)?->status,
                'arrival_time' => $marked->get($e->id)?->arrival_time,
                'remarks' => $marked->get($e->id)?->remarks,
            ])->sortBy('patient.name')->values(),
        ];
    }

    /** @param  list<array{enrollment_id: int, status: string, arrival_time?: ?string, remarks?: ?string}>  $entries */
    public function mark(TrainingGroup $group, Carbon $date, array $entries, User $user): int
    {
        if ($date->isFuture() && ! $date->isToday()) {
            throw ValidationException::withMessages(['date' => 'Attendance cannot be marked for a future date.']);
        }

        $isTrainerOnly = $user->hasRole(Role::Trainer->value) && ! $user->hasAnyRole([Role::SuperAdmin->value, Role::BranchAdmin->value]);
        if ($isTrainerOnly && $date->lt(today()->subDays(self::TRAINER_EDIT_DAYS))) {
            throw ValidationException::withMessages(['date' => 'Trainers can mark attendance for the last '.self::TRAINER_EDIT_DAYS.' days only. Ask the branch admin to correct older days.']);
        }

        $holiday = $group->isHoliday($date);
        $roster = $group->rosterOn($date)->pluck('patient_id', 'id');

        foreach ($entries as $i => $entry) {
            if (! $roster->has($entry['enrollment_id'])) {
                throw ValidationException::withMessages(["entries.$i.enrollment_id" => 'This student is not on the class roster for that day.']);
            }
            if ($holiday && $entry['status'] !== AttendanceStatus::Holiday->value) {
                throw ValidationException::withMessages(["entries.$i.status" => 'This day is a holiday.']);
            }
        }

        DB::transaction(function () use ($group, $date, $entries, $roster, $user) {
            foreach ($entries as $entry) {
                TrainingAttendance::updateOrCreate(
                    ['enrollment_id' => $entry['enrollment_id'], 'date' => $date->toDateString()],
                    [
                        'patient_id' => $roster[$entry['enrollment_id']],
                        'training_group_id' => $group->id,
                        'status' => $entry['status'],
                        'arrival_time' => $entry['status'] === AttendanceStatus::Late->value ? ($entry['arrival_time'] ?? null) : null,
                        'remarks' => $entry['remarks'] ?? null,
                        'marked_by' => $user->id,
                    ],
                );
            }
        });

        return count($entries);
    }

    /** One student's month: every marked day + summary (Plan §১২ example). */
    public function monthly(Enrollment $enrollment, Carbon $month): array
    {
        $days = TrainingAttendance::where('enrollment_id', $enrollment->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')->get();

        return [
            'month' => $month->format('Y-m'),
            'days' => $days->map(fn (TrainingAttendance $a) => ['date' => $a->date->toDateString(), 'status' => $a->status, 'remarks' => $a->remarks])->values(),
            'summary' => $this->summary($days->pluck('status')),
        ];
    }

    /** Class sheet: students × days for one month. */
    public function classMonth(TrainingGroup $group, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $rows = TrainingAttendance::with('patient:id,name,patient_code')
            ->where('training_group_id', $group->id)->whereBetween('date', [$start, $end])->get()->groupBy('enrollment_id');

        return [
            'month' => $month->format('Y-m'),
            'dates' => $rows->flatten()->pluck('date')->map->toDateString()->unique()->sort()->values(),
            'students' => $rows->map(fn (Collection $items, $enrollmentId) => [
                'enrollment_id' => $enrollmentId,
                'patient' => $items->first()->patient->only(['id', 'name', 'patient_code']),
                'days' => $items->mapWithKeys(fn ($a) => [$a->date->toDateString() => $a->status]),
                'summary' => $this->summary($items->pluck('status')),
            ])->sortBy('patient.name')->values(),
        ];
    }

    /** @param  Collection<int, AttendanceStatus>  $statuses */
    public function summary(Collection $statuses): array
    {
        $count = fn (AttendanceStatus $s) => $statuses->filter(fn ($x) => $x === $s)->count();
        $present = $count(AttendanceStatus::Present);
        $late = $count(AttendanceStatus::Late);
        $absent = $count(AttendanceStatus::Absent);
        $countable = $present + $late + $absent;

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'leave' => $count(AttendanceStatus::Leave),
            'holiday' => $count(AttendanceStatus::Holiday),
            'rate' => $countable ? (int) round(($present + $late) / $countable * 100) : null,
        ];
    }
}
