<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngredientRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');

        $branch = $this->currentBranch($request);

        $ingredients = Ingredient::query()
            ->with('unit')
            ->withStockFor($branch->id)
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/ingredients/Index', [
            'ingredients' => $ingredients,
            'units' => Unit::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'canManage' => $request->user('staff')?->can('inventory.manage'),
        ]);
    }

    public function store(IngredientRequest $request): RedirectResponse
    {
        Ingredient::query()->create($request->validated());

        return back()->with('success', __('inventory.ingredient_created'));
    }

    public function update(IngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $ingredient->update($request->validated());

        return back()->with('success', __('inventory.ingredient_updated'));
    }

    public function destroy(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('inventory.manage');

        $ingredient->update(['is_active' => false]);

        return back()->with('success', __('inventory.ingredient_deactivated'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
