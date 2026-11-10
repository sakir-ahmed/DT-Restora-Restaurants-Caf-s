<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The fixed units every restaurant starts with. Each type has one base unit
 * (g, l, pcs) that stock is counted in; the others are a multiple of it.
 * Owners add their own units (box, large box ...) next to these in Settings.
 */
final class StandardUnits
{
    public const TYPES = ['weight', 'volume', 'count'];

    /** Base unit symbol of each type. */
    public const BASE = ['weight' => 'g', 'volume' => 'l', 'count' => 'pcs'];

    /**
     * @return array<int, array{symbol: string, name: string, name_bn: string, type: string, factor: float}>
     */
    public static function all(): array
    {
        return [
            ['symbol' => 'g', 'name' => 'Gram', 'name_bn' => 'গ্রাম', 'type' => 'weight', 'factor' => 1],
            ['symbol' => 'mg', 'name' => 'Milligram', 'name_bn' => 'মিলিগ্রাম', 'type' => 'weight', 'factor' => 0.001],
            ['symbol' => 'kg', 'name' => 'Kilogram', 'name_bn' => 'কিলোগ্রাম', 'type' => 'weight', 'factor' => 1000],
            ['symbol' => 'l', 'name' => 'Litre', 'name_bn' => 'লিটার', 'type' => 'volume', 'factor' => 1],
            ['symbol' => 'ml', 'name' => 'Millilitre', 'name_bn' => 'মিলিলিটার', 'type' => 'volume', 'factor' => 0.001],
            ['symbol' => 'tsp', 'name' => 'Teaspoon', 'name_bn' => 'চা চামচ', 'type' => 'volume', 'factor' => 0.005],
            ['symbol' => 'tbsp', 'name' => 'Tablespoon', 'name_bn' => 'টেবিল চামচ', 'type' => 'volume', 'factor' => 0.015],
            ['symbol' => 'cup', 'name' => 'Cup', 'name_bn' => 'কাপ', 'type' => 'volume', 'factor' => 0.24],
            ['symbol' => 'pcs', 'name' => 'Piece', 'name_bn' => 'টি', 'type' => 'count', 'factor' => 1],
            ['symbol' => 'dozen', 'name' => 'Dozen', 'name_bn' => 'ডজন', 'type' => 'count', 'factor' => 12],
        ];
    }
}
