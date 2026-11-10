<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $gram = Unit::query()->updateOrCreate(
            ['symbol' => 'g'],
            ['name' => 'Gram', 'name_bn' => 'গ্রাম', 'type' => 'weight', 'base_unit_id' => null, 'factor' => 1],
        );

        Unit::query()->updateOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogram', 'name_bn' => 'কিলোগ্রাম', 'type' => 'weight', 'base_unit_id' => $gram->id, 'factor' => 1000],
        );

        Unit::query()->updateOrCreate(
            ['symbol' => 'mg'],
            ['name' => 'Milligram', 'name_bn' => 'মিলিগ্রাম', 'type' => 'weight', 'base_unit_id' => $gram->id, 'factor' => 0.001],
        );

        $litre = Unit::query()->updateOrCreate(
            ['symbol' => 'l'],
            ['name' => 'Litre', 'name_bn' => 'লিটার', 'type' => 'volume', 'base_unit_id' => null, 'factor' => 1],
        );

        Unit::query()->updateOrCreate(
            ['symbol' => 'ml'],
            ['name' => 'Millilitre', 'name_bn' => 'মিলিলিটার', 'type' => 'volume', 'base_unit_id' => $litre->id, 'factor' => 0.001],
        );

        foreach ([
            ['tsp', 'Teaspoon', 'চা চামচ', 0.005],
            ['tbsp', 'Tablespoon', 'টেবিল চামচ', 0.015],
            ['cup', 'Cup', 'কাপ', 0.24],
        ] as [$symbol, $name, $nameBn, $factor]) {
            Unit::query()->updateOrCreate(
                ['symbol' => $symbol],
                ['name' => $name, 'name_bn' => $nameBn, 'type' => 'volume', 'base_unit_id' => $litre->id, 'factor' => $factor],
            );
        }

        $piece = Unit::query()->updateOrCreate(
            ['symbol' => 'pcs'],
            ['name' => 'Piece', 'name_bn' => 'টি', 'type' => 'count', 'base_unit_id' => null, 'factor' => 1],
        );

        Unit::query()->updateOrCreate(
            ['symbol' => 'dozen'],
            ['name' => 'Dozen', 'name_bn' => 'ডজন', 'type' => 'count', 'base_unit_id' => $piece->id, 'factor' => 12],
        );
    }
}
