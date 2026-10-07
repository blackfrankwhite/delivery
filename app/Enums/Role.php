<?php

namespace App\Enums;

enum Role: string
{
    case Superadministrator = 'superadministrator';
    case Operator = 'operator';
    case Driver = 'driver';
}
