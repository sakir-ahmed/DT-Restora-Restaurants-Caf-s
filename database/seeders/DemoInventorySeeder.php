<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventory\ReceivePurchaseAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'testing') {
            return;
        }

        $branch = Branch::query()->where('code', 'BR01')->firstOrFail();
        $kitchen = User::query()->where('username', 'kitchen')->first();

        $supplier = Supplier::query()->updateOrCreate(
            ['name' => 'Karim Wholesale'],
            ['mobile' => '8801711100022', 'is_active' => true],
        );

        Supplier::query()->updateOrCreate(
            ['name' => 'Dhaka Vegetables Ltd'],
            ['mobile' => '8801711100033', 'is_active' => true],
        );

        $catalog = [
            ['name' => 'Chicken (whole)', 'name_bn' => 'মুরগি', 'unit' => 'kg', 'reorder' => 10],
            ['name' => 'Beef', 'name_bn' => 'গরুর মাংস', 'unit' => 'kg', 'reorder' => 8],
            ['name' => 'Basmati Rice', 'name_bn' => 'বাসমতী চাল', 'unit' => 'kg', 'reorder' => 25],
            ['name' => 'Onion', 'name_bn' => 'পেঁয়াজ', 'unit' => 'kg', 'reorder' => 15],
            ['name' => 'Tomato', 'name_bn' => 'টমেটো', 'unit' => 'kg', 'reorder' => 10],
            ['name' => 'Cooking Oil', 'name_bn' => 'সয়াবিন তেল', 'unit' => 'l', 'reorder' => 20],
            ['name' => 'Flour', 'name_bn' => 'ময়দা', 'unit' => 'kg', 'reorder' => 20],
            ['name' => 'Burger Bun', 'name_bn' => 'বান', 'unit' => 'pcs', 'reorder' => 50],
            ['name' => 'Cheese Slice', 'name_bn' => 'চিজ', 'unit' => 'pcs', 'reorder' => 40],
            ['name' => 'Milk', 'name_bn' => 'দুধ', 'unit' => 'l', 'reorder' => 15],
        ];

        $ingredientIds = [];

        foreach ($catalog as $entry) {
            $unit = Unit::query()->where('symbol', $entry['unit'])->firstOrFail();

            $ingredient = Ingredient::query()->updateOrCreate(
                ['name' => $entry['name']],
                ['name_bn' => $entry['name_bn'], 'unit_id' => $unit->id, 'reorder_level' => $entry['reorder'], 'is_active' => true],
            );

            $ingredientIds[] = ['id' => $ingredient->id, 'unit' => $unit->id];
        }

        // Opening stock purchase for BR01.
        $prices = [260, 640, 120, 45, 60, 165, 55, 25, 18, 90];

        $purchase = Purchase::query()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_no' => 'OPENING-001',
            'purchased_at' => now()->toDateString(),
            'status' => Purchase::STATUS_DRAFT,
            'user_id' => $kitchen?->id,
        ]);

        foreach ($ingredientIds as $index => $ref) {
            $qty = in_array($ref['unit'], [$ingredientIds[7]['unit'], $ingredientIds[8]['unit']], true) ? 200 : 50;

            $purchase->items()->create([
                'ingredient_id' => $ref['id'],
                'unit_id' => $ref['unit'],
                'qty' => $qty,
                'unit_cost' => $prices[$index],
                'line_total' => round($qty * $prices[$index], 2),
            ]);
        }

        app(ReceivePurchaseAction::class)->execute($purchase, $kitchen);
    }
}
