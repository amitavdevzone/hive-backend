<?php

namespace App\Enums;

enum ReminderType: string
{
    case Once = 'once';
    case Recurring = 'recurring';
}
