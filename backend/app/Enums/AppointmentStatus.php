<?php

namespace App\Enums;

/** Therapy appointment status (Plan §১৭). Never used for student attendance. */
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    /** Statuses that hold the therapist's time slot. */
    public static function live(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::CheckedIn->value, self::Completed->value];
    }

    /** action => [allowed from, to] */
    public static function transitions(): array
    {
        return [
            'confirm' => [[self::Pending], self::Confirmed],
            'check-in' => [[self::Pending, self::Confirmed], self::CheckedIn],
            'cancel' => [[self::Pending, self::Confirmed], self::Cancelled],
            'no-show' => [[self::Pending, self::Confirmed], self::NoShow],
        ];
    }
}
