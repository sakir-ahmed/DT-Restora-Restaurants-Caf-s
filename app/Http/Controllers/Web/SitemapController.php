<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\JobOpening;
use App\Models\MenuItem;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $body = '';

        foreach ($this->entries() as $entry) {
            $lastmod = $entry['lastmod'] !== null ? '<lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>' : '';
            $body .= '<url><loc>'.e($entry['loc']).'</loc>'.$lastmod.'</url>';
        }

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>',
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    /**
     * @return list<array{loc: string, lastmod: CarbonInterface|null}>
     */
    private function entries(): array
    {
        $entries = [];

        foreach ([url('/'), route('web.about'), route('web.gallery'), route('web.blog.index'), route('web.locations.index'), route('web.contact'), route('web.careers.index'), route('web.faq'), route('web.delivery'), route('web.refund'), route('web.privacy'), route('web.terms'), route('web.cookies')] as $loc) {
            $entries[] = ['loc' => $loc, 'lastmod' => null];
        }

        foreach (BlogPost::query()->published()->get(['slug', 'updated_at']) as $post) {
            $entries[] = ['loc' => route('web.blog.show', ['slug' => $post->slug]), 'lastmod' => $post->updated_at];
        }

        foreach (JobOpening::query()->open()->get(['id', 'title', 'updated_at']) as $opening) {
            $entries[] = ['loc' => route('web.careers.show', ['opening' => $opening->id, 'slug' => $opening->slug()]), 'lastmod' => $opening->updated_at];
        }

        $branches = Branch::query()->where('is_active', true)->orderBy('code')->get();

        foreach ($branches as $branch) {
            $entries[] = ['loc' => route('web.locations.show', ['slug' => $branch->publicSlug()]), 'lastmod' => $branch->updated_at];
            $entries[] = ['loc' => route('web.menu', ['branchCode' => strtolower($branch->code)]), 'lastmod' => $branch->updated_at];
        }

        $codes = $branches->pluck('code', 'id');

        $items = MenuItem::query()
            ->available()
            ->whereIn('branch_id', $codes->keys())
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'branch_id', 'updated_at']);

        foreach ($items as $item) {
            $entries[] = [
                'loc' => route('web.item', ['branchCode' => strtolower((string) $codes[$item->branch_id]), 'item' => $item->id]),
                'lastmod' => $item->updated_at,
            ];
        }

        return $entries;
    }
}
