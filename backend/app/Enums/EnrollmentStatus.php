<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Discontinued = 'discontinued';

    /** Enrollment still running or about to start — assigned staff keep access. */
    public static function open(): array
    {
        return [self::Pending->value, self::Active->value, self::OnHold->value];
    }

    /** Counts towards "Regular Student / Therapy Patient" labels and class capacity. */
    public static function current(): array
    {
        return [self::Active->value, self::OnHold->value];
    }

    public function isOpen(): bool
    {
        return in_array($this->value, self::open(), true);
    }

    /** Allowed status changes: action => [from statuses, to status]. */
    public static function transitions(): array
    {
        return [
            'activate' => [[self::Pending], self::Active],
            'hold' => [[self::Active], self::OnHold],
            'resume' => [[self::OnHold], self::Active],
            'complete' => [[self::Active, self::OnHold], self::Completed],
            'discontinue' => [[self::Pending, self::Active, self::OnHold], self::Discontinued],
        ];
    }
}
