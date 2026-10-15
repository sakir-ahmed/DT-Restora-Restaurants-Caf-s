<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuCategoryRequest;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('menu.view');

        $branch = $this->currentBranch($request);

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->ordered()
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->get();

        return Inertia::render('admin/menu/Index', [
            'categories' => $categories,
            'canManage' => $request->user('staff')?->can('menu.manage'),
        ]);
    }

    public function storeCategory(MenuCategoryRequest $request): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        MenuCategory::query()->create([
            ...$request->validated(),
            'branch_id' => $branch->id,
        ]);

        DataVersion::bump();

        return back()->with('success', __('menu.category_created'));
    }

    public function updateCategory(MenuCategoryRequest $request, MenuCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', __('menu.category_updated'));
    }

    public function destroyCategory(Request $request, MenuCategory $category): RedirectResponse
    {
        $this->authorize('menu.manage');

        if ($category->items()->exists()) {
            return back()->with('error', __('menu.category_delete_not_empty'));
        }

        $category->delete();

        return back()->with('success', __('menu.category_deleted'));
    }

    public function storeItem(MenuItemRequest $request): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        $category = MenuCategory::query()->findOrFail((int) $request->input('menu_category_id'));

        abort_unless($category->branch_id === $branch->id, 404);

        DB::transaction(function () use ($request, $branch, $category): void {
            $item = MenuItem::query()->create([
                ...collect($request->validated())->except(['variants', 'addons'])->all(),
                'branch_id' => $branch->id,
                'menu_category_id' => $category->id,
            ]);

            DataVersion::bump();

            $this->syncRelations($item, $request);
        });

        return back()->with('success', __('menu.item_created'));
    }

    public function updateItem(MenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $item): void {
            $item->update(collect($request->validated())->except(['variants', 'addons'])->all());

            $this->syncRelations($item, $request);
        });

        DataVersion::bump();

        activity()->performedOn($item)->causedBy($request->user('staff'))->log('menu.item_updated');

        return back()->with('success', __('menu.item_updated'));
    }

    public function destroyItem(Request $request, MenuItem $item): RedirectResponse
    {
        $this->authorize('menu.manage');

        $item->delete();

        activity()->performedOn($item)->causedBy($request->user('staff'))->log('menu.item_deleted');

        return back()->with('success', __('menu.item_deleted'));
    }

    private function syncRelations(MenuItem $item, MenuItemRequest $request): void
    {
        $variants = collect($request->validated('variants', []));
        $addOns = collect($request->validated('addons', []));

        $item->variants()->whereNotIn('id', $variants->pluck('id')->filter())->delete();
        $item->addOns()->whereNotIn('id', $addOns->pluck('id')->filter())->delete();

        foreach ($variants as $variant) {
            $item->variants()->updateOrCreate(
                ['id' => $variant['id'] ?? null],
                collect($variant)->except('id')->all(),
            );
        }

        foreach ($addOns as $addOn) {
            $item->addOns()->updateOrCreate(
                ['id' => $addOn['id'] ?? null],
                collect($addOn)->except('id')->all(),
            );
        }
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branchId = $user->branch_id ?? (int) session('branch_id', 0);

        $branch = Branch::query()->find($branchId);

        if ($branch === null) {
            $branch = Branch::query()->orderBy('id')->firstOrFail();
        }

        return $branch;
    }
}
