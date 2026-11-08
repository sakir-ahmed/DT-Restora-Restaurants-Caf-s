<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $ingredientId,
        public readonly float $availableQty,
        public readonly float $requestedQty,
    ) {
        parent::__construct(
            sprintf(
                'Insufficient stock for ingredient %d: available %.3f, requested %.3f.',
                $ingredientId,
                $availableQty,
                $requestedQty,
            ),
        );
    }
}
