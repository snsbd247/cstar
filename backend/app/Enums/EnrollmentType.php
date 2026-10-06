<?php

namespace App\Enums;

enum EnrollmentType: string
{
    case Training = 'training';
    case Therapy = 'therapy';

    public function label(): string
    {
        return match ($this) {
            self::Training => 'Regular Training',
            self::Therapy => 'Therapy',
        };
    }
}
