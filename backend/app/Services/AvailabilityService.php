<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Therapist;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Free time of a therapist (Plan §১৬): weekly schedule at the branch − leave − holidays − live appointments.
 */
class AvailabilityService
{
    /** @return array{closed: ?string, slots: list<array{start: string, end: string, available: bool}>} */
    public function slots(Therapist $therapist, int $branchId, Carbon $date, int $durationMin): array
    {
        if ($reason = $this->closedReason($therapist, $branchId, $date)) {
            return ['closed' => $reason, 'slots' => []];
        }

        $schedules = $therapist->schedules()->where('branch_id', $branchId)->where('weekday', $date->dayOfWeek)->get();
        if ($schedules->isEmpty()) {
            return ['closed' => 'The therapist does not work at this branch on this day.', 'slots' => []];
        }

        $booked = $this->bookedRanges($therapist->id, $date);
        $now = now();
        $slots = [];

        foreach ($schedules as $schedule) {
            $cursor = $date->copy()->setTimeFromTimeString($schedule->start_time);
            $end = $date->copy()->setTimeFromTimeString($schedule->end_time);

            while ($cursor->copy()->addMinutes($durationMin)->lte($end)) {
                $slotEnd = $cursor->copy()->addMinutes($durationMin);
                $free = $cursor->gt($now) && ! $this->overlapsAny($cursor, $slotEnd, $booked);
                $slots[] = ['start' => $cursor->format('H:i'), 'end' => $slotEnd->format('H:i'), 'available' => $free];
                $cursor->addMinutes($schedule->slot_minutes);
            }
        }

        return ['closed' => null, 'slots' => $slots];
    }

    /** Throws a validation error explaining why the slot cannot be booked. */
    public function assertBookable(Therapist $therapist, int $branchId, Carbon $start, Carbon $end, int $patientId, ?int $ignoreAppointmentId = null): void
    {
        $date = $start->copy()->startOfDay();

        if ($reason = $this->closedReason($therapist, $branchId, $date)) {
            throw ValidationException::withMessages(['date' => $reason]);
        }

        $withinSchedule = $therapist->schedules()
            ->where('branch_id', $branchId)->where('weekday', $date->dayOfWeek)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();
        if (! $withinSchedule) {
            throw ValidationException::withMessages(['start_time' => 'This time is outside the therapist\'s working hours at this branch.']);
        }

        if ($this->overlapsAny($start, $end, $this->bookedRanges($therapist->id, $date, $ignoreAppointmentId))) {
            throw ValidationException::withMessages(['start_time' => "{$therapist->name} already has an appointment at this time."]);
        }

        $patientBusy = Appointment::live()->where('patient_id', $patientId)->whereDate('date', $date)
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->where('start_time', '<', $end->format('H:i:s'))->where('end_time', '>', $start->format('H:i:s'))
            ->exists();
        if ($patientBusy) {
            throw ValidationException::withMessages(['start_time' => 'The child already has another appointment at this time.']);
        }
    }

    public function closedReason(Therapist $therapist, int $branchId, Carbon $date): ?string
    {
        if ($therapist->isOnLeave($date)) {
            return "{$therapist->name} is on leave on this day.";
        }

        $holiday = Holiday::whereDate('date', $date)->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->first();

        return $holiday ? "The center is closed: {$holiday->title}." : null;
    }

    /** @return list<array{0: Carbon, 1: Carbon}> */
    private function bookedRanges(int $therapistId, Carbon $date, ?int $ignoreId = null): array
    {
        return Appointment::live()->where('therapist_id', $therapistId)->whereDate('date', $date)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['start_time', 'end_time'])
            ->map(fn ($a) => [$date->copy()->setTimeFromTimeString($a->start_time), $date->copy()->setTimeFromTimeString($a->end_time)])
            ->all();
    }

    private function overlapsAny(Carbon $start, Carbon $end, array $ranges): bool
    {
        foreach ($ranges as [$s, $e]) {
            if ($start->lt($e) && $end->gt($s)) {
                return true;
            }
        }

        return false;
    }
}
