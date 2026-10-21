<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\SaveRecipeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecipeRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Services\CostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('recipes.manage');

        $branch = $this->currentBranch($request);

        $items = MenuItem::query()
            ->where('branch_id', $branch->id)
            ->with(['category:id,name', 'variants:id,menu_item_id,price', 'recipe.items.ingredient.unit'])
            ->orderBy('name')
            ->get();

        $ingredients = Ingredient::query()
            ->with('unit:id,symbol,type')
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id']);

        return Inertia::render('admin/recipes/Index', [
            'items' => $items,
            'ingredients' => $ingredients,
        ]);
    }

    public function store(RecipeRequest $request, SaveRecipeAction $action): RedirectResponse
    {
        $item = MenuItem::query()->findOrFail((int) $request->validated('menu_item_id'));

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($item->branch_id), 404);

        if ($request->validated('lines') === []) {
            Recipe::query()->where('menu_item_id', $item->id)->each(function (Recipe $recipe): void {
                $recipe->items()->delete();
                $recipe->delete();
            });

            return back()->with('success', __('inventory.recipe_removed'));
        }

        $action->execute(
            $item,
            $request->validated('lines'),
            $request->validated('note'),
            $user,
        );

        return back()->with('success', __('inventory.recipe_saved'));
    }

    public function cost(Request $request, MenuItem $item, CostingService $costing): JsonResponse
    {
        $this->authorize('recipes.manage');

        $branch = $this->currentBranch($request);

        abort_unless($item->branch_id === $branch->id, 404);

        return response()->json([
            'success' => true,
            'data' => $costing->recipeCost($item, $branch->id),
        ]);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
