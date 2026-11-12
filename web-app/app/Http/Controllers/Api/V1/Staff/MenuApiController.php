<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\DataVersion;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\DeletionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Menu categories and deleting dishes for the mobile app, with the same rules as the web menu page.
 */
class MenuApiController extends Controller
{
    use InteractsWithApi;

    public function categories(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.view');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success(MenuCategory::query()
            ->where('branch_id', $branchModel->id)
            ->ordered()
            ->withCount('items')
            ->get()
            ->map(fn (MenuCategory $category): array => $this->payload($category))
            ->all());
    }

    public function storeCategory(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $category = MenuCategory::query()->create($this->validated($request) + ['branch_id' => $branchModel->id]);

        DataVersion::bump();

        return $this->success($this->payload($category), __('menu.category_created'), 201);
    }

    public function updateCategory(Request $request, string $branch, MenuCategory $category): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($category->branch_id === $branchModel->id, 404);

        $category->update($this->validated($request));

        DataVersion::bump();

        return $this->success($this->payload($category->fresh()), __('menu.category_updated'));
    }

    public function destroyCategory(Request $request, string $branch, MenuCategory $category): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($category->branch_id === $branchModel->id, 404);

        $blockers = DeletionGuard::blockers([__('menu.related_items') => $category->items()]);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['category' => DeletionGuard::message($category->name, $blockers)]);
        }

        $category->delete();
        DataVersion::bump();

        return $this->success(null, __('menu.category_deleted'));
    }

    public function destroyItem(Request $request, string $branch, MenuItem $item): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'menu.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($item->branch_id === $branchModel->id, 404);

        $item->delete();
        DataVersion::bump();

        activity()->performedOn($item)->causedBy($user)->log('menu.item_deleted');

        return $this->success(null, __('menu.item_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        return $data + ['sort_order' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MenuCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'name_bn' => $category->name_bn,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'items_count' => $category->items_count ?? $category->items()->count(),
        ];
    }
}
