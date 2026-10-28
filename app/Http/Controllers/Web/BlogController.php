<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\SeoBuilder;
use App\Support\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function __construct(private readonly SeoBuilder $seo) {}

    public function index(Request $request): Response
    {
        $locale = app()->getLocale();
        $category = $request->string('category')->trim()->toString();

        $categories = BlogPost::query()->published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        /** @var LengthAwarePaginator<int, BlogPost> $posts */
        $posts = BlogPost::query()
            ->published()
            ->when($category !== '' && $categories->contains($category), fn ($query) => $query->where('category', $category))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $activeCategory = $category !== '' && $categories->contains($category) ? $category : null;
        $page = $posts->currentPage();
        $canonical = route('web.blog.index', array_filter(['category' => $activeCategory, 'page' => $page > 1 ? $page : null]));

        return Inertia::render('web/blog/Index', [
            'posts' => $posts->getCollection()->map(fn (BlogPost $post): array => $this->summary($post, $locale))->values(),
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'pagination' => [
                'current' => $page,
                'last' => $posts->lastPage(),
                'prev' => $page > 1 ? route('web.blog.index', array_filter(['category' => $activeCategory, 'page' => $page > 2 ? $page - 1 : null])) : null,
                'next' => $posts->hasMorePages() ? route('web.blog.index', array_filter(['category' => $activeCategory, 'page' => $page + 1])) : null,
            ],
            'seo' => $this->seo->page(
                title: $activeCategory ? __('site.seo_blog_category_title', ['category' => $activeCategory]) : __('site.seo_blog_title'),
                description: __('site.seo_blog_description'),
                canonical: $canonical,
                jsonLd: [[
                    '@type' => 'Blog',
                    'name' => __('site.seo_blog_title'),
                    'url' => route('web.blog.index'),
                    'blogPost' => $posts->getCollection()->map(fn (BlogPost $post): array => [
                        '@type' => 'BlogPosting',
                        'headline' => $post->text('title', $locale),
                        'url' => $post->url(),
                        'datePublished' => $post->published_at?->toIso8601String(),
                    ])->values()->all(),
                ]],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_blog'), 'url' => route('web.blog.index')],
                ],
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $locale = app()->getLocale();

        $post = BlogPost::query()->published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->orderByRaw('category = ? desc', [$post->category])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $title = (string) $post->text('title', $locale);
        $excerpt = (string) $post->text('excerpt', $locale);
        $image = $post->coverAbsoluteSrc();

        $seo = $this->seo->page(
            title: $title,
            description: $excerpt,
            canonical: $post->url(),
            jsonLd: [array_filter([
                '@type' => 'BlogPosting',
                'headline' => mb_substr($title, 0, 110),
                'description' => $excerpt,
                'image' => $image ? [$image] : null,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at->toIso8601String(),
                'inLanguage' => $locale === 'bn' ? 'bn-BD' : 'en-BD',
                'articleSection' => $post->category,
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url()],
                'author' => ['@type' => 'Organization', 'name' => (string) config('app.name'), 'url' => url('/')],
                'publisher' => ['@type' => 'Organization', 'name' => (string) config('app.name'), 'logo' => ['@type' => 'ImageObject', 'url' => Brand::logo()]],
            ])],
            breadcrumbs: [
                ['name' => __('site.nav_home'), 'url' => url('/')],
                ['name' => __('site.nav_blog'), 'url' => route('web.blog.index')],
                ['name' => $title, 'url' => $post->url()],
            ],
            image: $image,
        );
        $seo['type'] = 'article';

        return Inertia::render('web/blog/Show', [
            'post' => $this->summary($post, $locale) + [
                'html' => $post->html($locale),
                'updated_at' => $post->updated_at->toIso8601String(),
            ],
            'related' => $related->map(fn (BlogPost $entry): array => $this->summary($entry, $locale))->values(),
            'seo' => $seo,
        ]);
    }

    public function feed(): HttpResponse
    {
        $items = BlogPost::query()->published()->orderByDesc('published_at')->limit(20)->get()->map(function (BlogPost $post): string {
            return '<item>'
                .'<title>'.e($post->title).'</title>'
                .'<link>'.e($post->url()).'</link>'
                .'<guid isPermaLink="true">'.e($post->url()).'</guid>'
                .'<pubDate>'.$post->published_at?->toRssString().'</pubDate>'
                .($post->category ? '<category>'.e($post->category).'</category>' : '')
                .'<description>'.e($post->excerpt).'</description>'
                .'</item>';
        })->join('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>'
            .'<title>'.e((string) config('app.name')).' - '.e(__('site.nav_blog')).'</title>'
            .'<link>'.e(route('web.blog.index')).'</link>'
            .'<description>'.e(__('site.seo_blog_description')).'</description>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(BlogPost $post, string $locale): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->text('title', $locale),
            'excerpt' => $post->text('excerpt', $locale),
            'category' => $post->category,
            'cover' => $post->coverSrc(),
            'cover_alt' => $post->cover_alt ?: $post->text('title', $locale),
            'published_at' => $post->published_at?->toIso8601String(),
            'reading_minutes' => $post->readingMinutes($locale),
        ];
    }
}
