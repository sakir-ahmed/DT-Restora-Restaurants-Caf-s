<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Save a menu item recipe: ingredient lines. A line may carry the unit
 * it was typed in (g, kg, mg, ml, l ...); it is converted to the base unit
 * the recipe and stock are kept in. A line without a unit is already in
 * base units. One recipe per item, fully replaced on each save.
 *
 * @phpstan-type RecipeLine array{ingredient_id: int, qty: string|float, unit_id?: int|string|null}
 */
class SaveRecipeAction
{
    /**
     * @param  Collection<int, RecipeLine>|array<int, RecipeLine>  $lines
     */
    public function execute(MenuItem $item, array|Collection $lines, ?string $note = null, ?User $user = null): Recipe
    {
        $lines = collect($lines);

        return DB::transaction(function () use ($item, $lines, $note, $user): Recipe {
            $ingredientIds = $lines->pluck('ingredient_id')->unique()->all();

            $ingredients = Ingredient::query()->with('unit')->whereKey($ingredientIds)->get()->keyBy('id');
            $units = Unit::query()->whereIn('id', $lines->pluck('unit_id')->filter()->unique()->all())->get()->keyBy('id');

            if (count($ingredients) !== count($ingredientIds)) {
                throw ValidationException::withMessages([
                    'lines' => __('inventory.unknown_ingredient'),
                ]);
            }

            $recipe = Recipe::query()->updateOrCreate(
                ['menu_item_id' => $item->id],
                ['note' => $note],
            );

            $recipe->items()->delete();

            foreach ($lines as $line) {
                $qty = (float) $line['qty'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('inventory.recipe_qty_positive'),
                    ]);
                }

                if (! empty($line['unit_id'])) {
                    $unit = $units->get((int) $line['unit_id']);
                    $ingredientUnit = $ingredients->get((int) $line['ingredient_id'])->unit;

                    if ($unit === null || ! $unit->isCompatibleWith($ingredientUnit)) {
                        throw ValidationException::withMessages([
                            'lines' => __('inventory.unit_incompatible'),
                        ]);
                    }

                    $qty = $unit->toBase($qty);
                }

                $recipe->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'qty' => $qty,
                ]);
            }

            activity()
                ->performedOn($recipe)
                ->causedBy($user)
                ->log('recipe.saved');

            return $recipe->load('items.ingredient.unit');
        });
    }
}
