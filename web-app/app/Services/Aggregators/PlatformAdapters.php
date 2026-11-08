<?php

declare(strict_types=1);

namespace App\Services\Aggregators;

use App\Models\AggregatorChannel;
use App\Services\Aggregators\Foodpanda\FoodpandaAdapter;
use App\Services\Aggregators\UberEats\UberEatsAdapter;

/**
 * Picks the live API adapter of a delivery platform, if it has one and
 * its credentials are filled in.
 */
class PlatformAdapters
{
    public static function for(?AggregatorChannel $channel): FoodpandaAdapter|UberEatsAdapter|null
    {
        if ($channel === null || ! $channel->hasApiCredentials()) {
            return null;
        }

        return match ($channel->key) {
            AggregatorChannel::FOODPANDA => app(FoodpandaAdapter::class, ['channel' => $channel]),
            AggregatorChannel::UBEREATS => app(UberEatsAdapter::class, ['channel' => $channel]),
            default => null,
        };
    }
}
