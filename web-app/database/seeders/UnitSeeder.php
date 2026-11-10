<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use App\Support\StandardUnits;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UnitSeeder extends Seeder
{
    /**
     * Create or restore the fixed standard units. Safe to run again: it never
     * touches the units an owner added.
     */
    public function run(): void
    {
        $bases = [];

        foreach (StandardUnits::all() as $definition) {
            $isBase = $definition['symbol'] === StandardUnits::BASE[$definition['type']];

            $unit = Unit::query()->updateOrCreate(
                ['symbol' => $definition['symbol']],
                [
                    'name' => $definition['name'],
                    'name_bn' => $definition['name_bn'],
                    'type' => $definition['type'],
                    'base_unit_id' => $isBase ? null : ($bases[$definition['type']] ?? Unit::query()->where('symbol', StandardUnits::BASE[$definition['type']])->value('id')),
                    'factor' => $definition['factor'],
                    // The column arrives in a later migration; this seeder also runs from an earlier one.
                    ...(Schema::hasColumn('units', 'is_system') ? ['is_system' => true] : []),
                ],
            );

            if ($isBase) {
                $bases[$definition['type']] = $unit->id;
            }
        }
    }
}
