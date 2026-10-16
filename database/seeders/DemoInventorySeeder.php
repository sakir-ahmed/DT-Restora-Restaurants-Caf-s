<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventory\ApproveStockTransferAction;
use App\Actions\Inventory\CloseStockCountAction;
use App\Actions\Inventory\LogWastageAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Inventory\SaveRecipeAction;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\MenuItem;
use App\Models\Purchase;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $kitchen = User::query()->where('username', 'kitchen')->first();
        $manager = User::query()->where('username', 'manager')->first();
        $units = Unit::query()->get()->keyBy('symbol');
        $branches = Branch::query()->whereIn('code', ['BR01', 'BR02', 'BR03'])->get()->keyBy('code');

        $suppliers = [
            Supplier::query()->updateOrCreate(
                ['name' => 'Karim Wholesale'],
                ['name_bn' => 'করিম হোলসেল', 'mobile' => '8801711100022', 'address' => 'Kawran Bazar, Dhaka', 'is_active' => true],
            ),
            Supplier::query()->updateOrCreate(
                ['name' => 'Dhaka Vegetables Ltd'],
                ['name_bn' => 'ঢাকা ভেজিটেবলস', 'mobile' => '8801711100033', 'address' => 'Shyambazar, Dhaka', 'is_active' => true],
            ),
            Supplier::query()->updateOrCreate(
                ['name' => 'Purbani Dairy'],
                ['name_bn' => 'পূর্বাণী ডেইরি', 'mobile' => '8801711100044', 'address' => 'Tejgaon, Dhaka', 'is_active' => true],
            ),
            Supplier::query()->updateOrCreate(
                ['name' => 'Cafe Bean Co.'],
                ['name_bn' => 'ক্যাফে বিন কোং', 'mobile' => '8801711100055', 'address' => 'Gulshan 1, Dhaka', 'is_active' => true],
            ),
        ];

        $catalog = [
            ['Chicken', 'মুরগি', 'kg', 8, 280],
            ['Beef', 'গরুর মাংস', 'kg', 6, 680],
            ['Mutton', 'খাসির মাংস', 'kg', 4, 980],
            ['Prawn', 'চিংড়ি', 'kg', 3, 750],
            ['Rui Fish', 'রুই মাছ', 'kg', 4, 320],
            ['Basmati Rice', 'বাসমতী চাল', 'kg', 20, 140],
            ['Kalijira Rice', 'কালিজিরা চাল', 'kg', 15, 120],
            ['Onion', 'পেঁয়াজ', 'kg', 12, 55],
            ['Potato', 'আলু', 'kg', 10, 30],
            ['Tomato', 'টমেটো', 'kg', 8, 60],
            ['Flour', 'ময়দা', 'kg', 10, 48],
            ['Cooking Oil', 'সয়াবিন তেল', 'l', 12, 175],
            ['Milk', 'দুধ', 'l', 10, 90],
            ['Yogurt', 'দই', 'kg', 6, 140],
            ['Butter', 'মাখন', 'kg', 2, 900],
            ['Cream', 'ক্রিম', 'l', 3, 420],
            ['Egg', 'ডিম', 'pcs', 80, 14],
            ['Burger Bun', 'বান', 'pcs', 40, 18],
            ['Cheese Slice', 'চিজ', 'pcs', 30, 22],
            ['Pizza Base', 'পিজা বেস', 'pcs', 20, 45],
            ['Coffee Beans', 'কফি বিন', 'kg', 2, 1800],
            ['Tea Leaf', 'চা পাতা', 'kg', 1, 450],
            ['Sugar', 'চিনি', 'kg', 8, 110],
            ['Paneer', 'পনির', 'kg', 3, 520],
            ['Pasta', 'পাস্তা', 'kg', 4, 180],
            ['Noodle', 'নুডুলস', 'kg', 4, 90],
            ['Mint', 'পুদিনা', 'kg', 1, 200],
            ['Lemon', 'লেবু', 'kg', 3, 120],
        ];

        $ingredients = [];

        foreach ($catalog as [$name, $nameBn, $symbol, $reorder, $cost]) {
            $unit = $units->get($symbol);

            if ($unit === null) {
                continue;
            }

            $ingredient = Ingredient::query()->updateOrCreate(
                ['name' => $name],
                [
                    'name_bn' => $nameBn,
                    'unit_id' => $unit->id,
                    'reorder_level' => $reorder,
                    'is_active' => true,
                ],
            );

            $ingredients[$name] = ['model' => $ingredient, 'unit' => $unit, 'cost' => $cost, 'reorder' => $reorder];
        }

        Ingredient::query()->whereNotIn('name', array_keys($ingredients))->update(['is_active' => false]);

        $opening = [
            'Chicken' => 25, 'Beef' => 12, 'Mutton' => 8, 'Prawn' => 6, 'Rui Fish' => 8,
            'Basmati Rice' => 40, 'Kalijira Rice' => 30, 'Onion' => 20, 'Potato' => 18, 'Tomato' => 10,
            'Flour' => 15, 'Cooking Oil' => 20, 'Milk' => 6, 'Yogurt' => 8, 'Butter' => 3,
            'Cream' => 4, 'Egg' => 120, 'Burger Bun' => 24, 'Cheese Slice' => 18, 'Pizza Base' => 12,
            'Coffee Beans' => 3, 'Tea Leaf' => 2, 'Sugar' => 10, 'Paneer' => 4, 'Pasta' => 6,
            'Noodle' => 6, 'Mint' => 1, 'Lemon' => 4,
        ];

        foreach ($branches as $branch) {
            $invoice = 'OPENING-'.$branch->code;

            if (Purchase::query()->where('invoice_no', $invoice)->exists()) {
                continue;
            }

            $purchase = Purchase::query()->create([
                'branch_id' => $branch->id,
                'supplier_id' => $suppliers[0]->id,
                'invoice_no' => $invoice,
                'purchased_at' => now()->subDays(12)->toDateString(),
                'status' => Purchase::STATUS_DRAFT,
                'user_id' => $kitchen?->id,
                'note' => 'Opening stock for '.$branch->name,
            ]);

            foreach ($opening as $name => $qty) {
                $row = $ingredients[$name];
                $purchase->items()->create([
                    'ingredient_id' => $row['model']->id,
                    'unit_id' => $row['unit']->id,
                    'qty' => $qty,
                    'unit_cost' => $row['cost'],
                    'line_total' => round($qty * $row['cost'], 2),
                ]);
            }

            app(ReceivePurchaseAction::class)->execute($purchase, $kitchen);
        }

        $this->restock($branches->get('BR01'), $suppliers[1], $ingredients, ['Tomato' => 8, 'Onion' => 15, 'Potato' => 12, 'Mint' => 1, 'Lemon' => 3], 'VEG-1044', now()->subDays(3));
        $this->restock($branches->get('BR01'), $suppliers[3], $ingredients, ['Coffee Beans' => 2, 'Milk' => 10, 'Cream' => 3], 'BEAN-220', now()->subDays(2));
        $this->restock($branches->get('BR02'), $suppliers[0], $ingredients, ['Chicken' => 10, 'Burger Bun' => 40, 'Cheese Slice' => 30], 'KAR-7781', now()->subDays(4));

        $this->recipes($ingredients);
        $this->wastage($branches->get('BR01'), $ingredients, $kitchen);
        $this->counts($branches->get('BR01'), $ingredients, $kitchen);
        $this->transfer($branches->get('BR01'), $branches->get('BR02'), $ingredients, $manager, $kitchen);
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit, cost: int, reorder: int}>  $ingredients
     * @param  array<string, int>  $lines
     */
    private function restock(?Branch $branch, Supplier $supplier, array $ingredients, array $lines, string $invoice, \DateTimeInterface $date): void
    {
        if ($branch === null || Purchase::query()->where('invoice_no', $invoice)->exists()) {
            return;
        }

        $purchase = Purchase::query()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_no' => $invoice,
            'purchased_at' => $date->format('Y-m-d'),
            'status' => Purchase::STATUS_DRAFT,
            'note' => 'Market restock',
        ]);

        foreach ($lines as $name => $qty) {
            $row = $ingredients[$name];
            $purchase->items()->create([
                'ingredient_id' => $row['model']->id,
                'unit_id' => $row['unit']->id,
                'qty' => $qty,
                'unit_cost' => $row['cost'],
                'line_total' => round($qty * $row['cost'], 2),
            ]);
        }

        app(ReceivePurchaseAction::class)->execute($purchase);
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit, cost: int, reorder: int}>  $ingredients
     */
    private function recipes(array $ingredients): void
    {
        $maps = [
            'Chicken Biryani' => [['Chicken', 0.22], ['Basmati Rice', 0.18], ['Onion', 0.04], ['Yogurt', 0.03]],
            'Kacchi Biryani' => [['Mutton', 0.25], ['Basmati Rice', 0.18], ['Onion', 0.05], ['Yogurt', 0.04]],
            'Beef Tehari' => [['Beef', 0.16], ['Kalijira Rice', 0.18], ['Potato', 0.08]],
            'Chicken Burger' => [['Chicken', 0.12], ['Burger Bun', 1], ['Cheese Slice', 1]],
            'Beef Burger' => [['Beef', 0.14], ['Burger Bun', 1]],
            'Margherita Pizza' => [['Pizza Base', 1], ['Cheese Slice', 3], ['Tomato', 0.04]],
            'Cappuccino' => [['Coffee Beans', 0.018], ['Milk', 0.15]],
            'Masala Tea' => [['Tea Leaf', 0.004], ['Milk', 0.08], ['Sugar', 0.01]],
            'Dal Bhuna' => [['Cooking Oil', 0.02]],
            'Butter Chicken' => [['Chicken', 0.20], ['Butter', 0.025], ['Cream', 0.04], ['Tomato', 0.06]],
            'Chicken Tikka' => [['Chicken', 0.18], ['Yogurt', 0.04]],
            'Pasta Alfredo' => [['Pasta', 0.12], ['Cream', 0.06], ['Butter', 0.015]],
        ];

        $action = app(SaveRecipeAction::class);

        foreach ($maps as $dish => $lines) {
            $item = MenuItem::query()->where('name', $dish)->whereHas('branch', fn ($query) => $query->where('code', 'BR01'))->first();

            if ($item === null) {
                continue;
            }

            $payload = [];

            foreach ($lines as [$name, $qty]) {
                $ingredient = $ingredients[$name]['model'];
                $payload[] = [
                    'ingredient_id' => $ingredient->id,
                    'qty' => $ingredients[$name]['unit']->toBase((string) $qty),
                ];
            }

            $action->execute($item, $payload, 'House recipe for one regular plate.');
        }
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit, cost: int, reorder: int}>  $ingredients
     */
    private function wastage(?Branch $branch, array $ingredients, ?User $user): void
    {
        if ($branch === null || Wastage::query()->where('note', 'like', 'Demo:%')->exists()) {
            return;
        }

        $action = app(LogWastageAction::class);
        $rows = [
            ['Tomato', 1.5, 'spoiled', 'Demo: Saturday market tomatoes turned soft.'],
            ['Milk', 2, 'spoiled', 'Demo: Open milk left out after close.'],
            ['Chicken', 0.4, 'kitchen_mistake', 'Demo: Over-salted tikka, could not send.'],
            ['Burger Bun', 8, 'dropped', 'Demo: A tray slipped at the pass.'],
            ['Yogurt', 0.5, 'over_production', 'Demo: Extra borhani from Friday lunch.'],
        ];

        foreach ($rows as [$name, $qty, $reason, $note]) {
            $action->execute($branch, $ingredients[$name]['model'], $ingredients[$name]['unit'], (float) $qty, $reason, $note, $user);
        }
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit, cost: int, reorder: int}>  $ingredients
     */
    private function counts(?Branch $branch, array $ingredients, ?User $user): void
    {
        if ($branch === null || StockCount::query()->where('note', 'like', 'Demo:%')->exists()) {
            return;
        }

        $closed = StockCount::query()->create([
            'branch_id' => $branch->id,
            'status' => StockCount::STATUS_OPEN,
            'note' => 'Demo: Sunday night count',
            'user_id' => $user?->id,
        ]);

        foreach (['Chicken', 'Basmati Rice', 'Onion', 'Egg'] as $name) {
            $stock = IngredientStock::query()
                ->where('branch_id', $branch->id)
                ->where('ingredient_id', $ingredients[$name]['model']->id)
                ->first();
            $system = (float) ($stock->stock_qty ?? 0);

            $closed->items()->create([
                'ingredient_id' => $ingredients[$name]['model']->id,
                'system_qty' => $system,
                'counted_qty' => max(0, $system - ($name === 'Egg' ? 3 : 0)),
            ]);
        }

        app(CloseStockCountAction::class)->execute($closed, $user);

        $open = StockCount::query()->create([
            'branch_id' => $branch->id,
            'status' => StockCount::STATUS_OPEN,
            'note' => 'Demo: This morning, still counting the cold room',
            'user_id' => $user?->id,
        ]);

        foreach (['Milk', 'Tomato', 'Burger Bun'] as $name) {
            $stock = IngredientStock::query()
                ->where('branch_id', $branch->id)
                ->where('ingredient_id', $ingredients[$name]['model']->id)
                ->first();

            $open->items()->create([
                'ingredient_id' => $ingredients[$name]['model']->id,
                'system_qty' => (float) ($stock->stock_qty ?? 0),
                'counted_qty' => $name === 'Milk' ? null : (float) ($stock->stock_qty ?? 0),
            ]);
        }
    }

    /**
     * @param  array<string, array{model: Ingredient, unit: Unit, cost: int, reorder: int}>  $ingredients
     */
    private function transfer(?Branch $from, ?Branch $to, array $ingredients, ?User $manager, ?User $kitchen): void
    {
        if ($from === null || $to === null || StockTransfer::query()->where('note', 'like', 'Demo:%')->exists()) {
            return;
        }

        $transfer = StockTransfer::query()->create([
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'status' => StockTransfer::STATUS_REQUESTED,
            'note' => 'Demo: Rice and chicken for the Dhanmondi weekend',
            'requested_by' => $manager?->id,
        ]);

        foreach (['Basmati Rice' => 5, 'Chicken' => 4] as $name => $qty) {
            $transfer->items()->create([
                'ingredient_id' => $ingredients[$name]['model']->id,
                'unit_id' => $ingredients[$name]['unit']->id,
                'qty' => $qty,
            ]);
        }

        $approved = app(ApproveStockTransferAction::class)->execute($transfer, $manager);
        app(ReceiveStockTransferAction::class)->execute($approved, $kitchen);
    }
}
