<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Paid = 'paid';
    case Void = 'void';

    public function isKitchenActive(): bool
    {
        return in_array($this, [self::Pending, self::Preparing, self::Ready, self::Served], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Preparing, self::Ready, self::Served, self::Paid, self::Void], true),
            self::Preparing => in_array($next, [self::Ready, self::Served, self::Paid, self::Void], true),
            self::Ready => in_array($next, [self::Served, self::Paid, self::Void], true),
            self::Served => in_array($next, [self::Paid, self::Void], true),
            self::Paid => $next === self::Void,
            self::Void => false,
        };
    }
}
