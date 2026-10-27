<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderChannel: string
{
    case Pos = 'pos';
    case Website = 'website';
    case Qr = 'qr';
    case Aggregator = 'aggregator';
}
