<?php

namespace App\Enums;

/** Regular-student attendance (Plan §১২). Never used for therapy appointments. */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';
    case Holiday = 'holiday';

    /** Attended the class — a training record may be written. */
    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
