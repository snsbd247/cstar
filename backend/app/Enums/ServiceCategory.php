<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Therapy = 'therapy';
    case Training = 'training';
    case Assessment = 'assessment';
    case Consultation = 'consultation';
}
