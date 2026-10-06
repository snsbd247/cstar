<?php

namespace App\Enums;

enum PatientStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Discharged = 'discharged';
    case Inactive = 'inactive';
}
