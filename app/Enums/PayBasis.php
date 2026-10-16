<?php

declare(strict_types=1);

namespace App\Enums;

enum PayBasis: string
{
    case Monthly = 'monthly';
    case Daily = 'daily';
    case Hourly = 'hourly';
}
