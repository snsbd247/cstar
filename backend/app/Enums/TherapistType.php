<?php

namespace App\Enums;

enum TherapistType: string
{
    case SpeechLanguage = 'slt';
    case Occupational = 'ot';
    case Aba = 'aba';
    case OralPlacement = 'opt';
    case SpecialEducator = 'special_educator';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SpeechLanguage => 'Speech & Language Therapist',
            self::Occupational => 'Occupational Therapist',
            self::Aba => 'ABA Therapist',
            self::OralPlacement => 'OPT Therapist',
            self::SpecialEducator => 'Special Educator',
            self::Other => 'Other Specialist',
        };
    }
}
