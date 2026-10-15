<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function show(string $branchCode, MenuItem $item): Response
    {
        $branch = Branch::query()
            ->where('is_active', true)
            ->where('code', strtoupper($branchCode))
            ->firstOrFail();

        abort_unless($item->branch_id === $branch->id && $item->is_available, 404);

        $item->load(['variants', 'addOns', 'category:id,name']);

        return Inertia::render('web/Item', [
            'branch' => $branch->only(['id', 'name', 'code']),
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'description' => $item->description,
                'base_price' => (string) $item->base_price,
                'is_vegetarian' => $item->is_vegetarian,
                'photo_path' => $item->photo_path,
                'category' => $item->category?->name,
                'variants' => $item->variants,
                'add_ons' => $item->addOns,
            ],
        ])->withViewData(['seo' => [
            'title' => $item->name.' - '.$branch->name,
            'description' => $item->description ?: $item->name,
            'canonical' => route('web.item', ['branchCode' => strtolower($branch->code), 'item' => $item->id]),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'MenuItem',
                'name' => $item->name,
                'description' => $item->description,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $item->base_price,
                    'priceCurrency' => 'BDT',
                ],
            ],
        ]]);
    }
}
