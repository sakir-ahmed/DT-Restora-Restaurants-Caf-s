<?php

declare(strict_types=1);

namespace App\Enums;

enum DemoStage: string
{
    case Visited = 'visited';
    case BrowsedMenu = 'browsed_menu';
    case AddedToCart = 'added_to_cart';
    case Checkout = 'checkout';
    case Ordered = 'ordered';
    case TriedAdmin = 'tried_admin';

    public function rank(): int
    {
        return match ($this) {
            self::Visited => 0,
            self::BrowsedMenu => 1,
            self::AddedToCart => 2,
            self::Checkout => 3,
            self::Ordered => 4,
            self::TriedAdmin => 5,
        };
    }
}
