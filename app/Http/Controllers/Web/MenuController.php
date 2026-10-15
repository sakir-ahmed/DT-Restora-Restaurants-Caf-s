<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __invoke(Request $request, ?string $branchCode = null): Response
    {
        $branch = Branch::query()
            ->where('is_active', true)
            ->when($branchCode !== null, fn ($query) => $query->where('code', strtoupper($branchCode)))
            ->when($branchCode === null, fn ($query) => $query->orderBy('id'))
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns'])->orderBy('sort_order')])
            ->get();

        // QR table ordering: /menu?table=T3 or /q/{code}
        $tableCode = $request->string('table')->toString();
        $table = $tableCode !== ''
            ? RestaurantTable::query()->where('branch_id', $branch->id)->where('name', $tableCode)->first()
            : null;

        if ($tableCode !== '' && $table === null) {
            abort(404);
        }

        session(['web_branch_id' => $branch->id]);

        return Inertia::render('web/Menu', [
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'address', 'phone', 'opening_hours']),
            'categories' => $categories,
            'table' => $table?->only(['id', 'name']),
        ])->withViewData(['seo' => $this->seo($branch)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seo(Branch $branch): array
    {
        return [
            'title' => $branch->displayName('en').' - '.__('web.seo_menu_title'),
            'description' => __('web.seo_menu_description', ['branch' => $branch->name]),
            'canonical' => route('web.menu', ['branchCode' => strtolower($branch->code)]),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Menu',
                'name' => $branch->name.' Menu',
                'hasMenuSection' => $branch->menuCategories->map(fn ($category) => [
                    '@type' => 'MenuSection',
                    'name' => $category->name,
                ])->all(),
            ],
        ];
    }
}
