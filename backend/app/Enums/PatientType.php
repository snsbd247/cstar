<?php

namespace App\Enums;

/**
 * UI label only — always DERIVED from current enrollments, never stored (PATIENT ≠ STUDENT).
 */
enum PatientType: string
{
    case Student = 'student';
    case TherapyPatient = 'therapy';
    case StudentAndTherapy = 'both';
    case Registered = 'none';

    public static function fromCounts(int $training, int $therapy): self
    {
        return match (true) {
            $training > 0 && $therapy > 0 => self::StudentAndTherapy,
            $training > 0 => self::Student,
            $therapy > 0 => self::TherapyPatient,
            default => self::Registered,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Regular Student',
            self::TherapyPatient => 'Therapy Patient',
            self::StudentAndTherapy => 'Student + Therapy',
            self::Registered => 'Registered (no enrollment)',
        };
    }
}
