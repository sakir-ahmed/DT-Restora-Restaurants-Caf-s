<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $entries = collect([
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('web.about'), 'lastmod' => null],
            ['loc' => route('web.locations.index'), 'lastmod' => null],
            ['loc' => route('web.contact'), 'lastmod' => null],
            ['loc' => route('web.privacy'), 'lastmod' => null],
            ['loc' => route('web.terms'), 'lastmod' => null],
        ]);

        $branches = Branch::query()->where('is_active', true)->orderBy('code')->get();

        foreach ($branches as $branch) {
            $entries->push(['loc' => route('web.locations.show', ['slug' => $branch->publicSlug()]), 'lastmod' => $branch->updated_at]);
            $entries->push(['loc' => route('web.menu', ['branchCode' => strtolower($branch->code)]), 'lastmod' => $branch->updated_at]);
        }

        $codes = $branches->pluck('code', 'id');

        MenuItem::query()
            ->available()
            ->whereIn('branch_id', $codes->keys())
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'branch_id', 'updated_at'])
            ->each(function (MenuItem $item) use ($entries, $codes): void {
                $entries->push([
                    'loc' => route('web.item', ['branchCode' => strtolower((string) $codes[$item->branch_id]), 'item' => $item->id]),
                    'lastmod' => $item->updated_at,
                ]);
            });

        $body = $entries->map(function (array $entry): string {
            $lastmod = $entry['lastmod'] ? '<lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>' : '';

            return '<url><loc>'.e($entry['loc']).'</loc>'.$lastmod.'</url>';
        })->join('');

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}
