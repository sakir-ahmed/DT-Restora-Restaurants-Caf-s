<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngredientCategoryRequest;
use App\Models\IngredientCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngredientCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');

        return Inertia::render('admin/ingredient-categories/Index', [
            'categories' => IngredientCategory::query()->withCount('ingredients')->ordered()->get(),
            'canManage' => $request->user('staff')?->can('inventory.manage'),
        ]);
    }

    public function store(IngredientCategoryRequest $request): RedirectResponse
    {
        IngredientCategory::query()->create([...$request->validated(), 'sort_order' => (int) $request->validated('sort_order', 0)]);

        return back()->with('success', __('inventory.category_created'));
    }

    public function update(IngredientCategoryRequest $request, IngredientCategory $category): RedirectResponse
    {
        $category->update([...$request->validated(), 'sort_order' => (int) $request->validated('sort_order', 0)]);

        return back()->with('success', __('inventory.category_updated'));
    }

    public function destroy(Request $request, IngredientCategory $category): RedirectResponse
    {
        $this->authorize('inventory.manage');

        if ($category->ingredients()->exists()) {
            return back()->with('error', __('inventory.category_delete_not_empty'));
        }

        $category->delete();

        return back()->with('success', __('inventory.category_deleted'));
    }
}
