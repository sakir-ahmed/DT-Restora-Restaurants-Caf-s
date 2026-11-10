<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\User;
use App\Support\Brand;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);
    Storage::fake('public');

    $this->owner = User::factory()->role('owner')->create();
});

/**
 * @return array<int, array<string, mixed>>
 */
function blogGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? '{}', true)['@graph'] ?? [];
}

it('lists only published posts, newest first', function (): void {
    BlogPost::factory()->create(['title' => 'Older post', 'published_at' => now()->subDays(5)]);
    BlogPost::factory()->create(['title' => 'Newest post', 'published_at' => now()->subHour()]);
    BlogPost::factory()->draft()->create(['title' => 'Secret draft']);
    BlogPost::factory()->scheduled()->create(['title' => 'Coming later']);

    $response = $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('web/blog/Index')->has('posts', 2)->where('posts.0.title', 'Newest post'));

    expect($response->getContent())->not->toContain('Secret draft')->not->toContain('Coming later');
});

it('filters by category and ignores unknown categories', function (): void {
    BlogPost::factory()->create(['title' => 'A recipe', 'category' => 'Recipes']);
    BlogPost::factory()->create(['title' => 'Some news', 'category' => 'News']);

    $this->get('/blog?category=Recipes')->assertInertia(fn ($page) => $page
        ->has('posts', 1)->where('posts.0.title', 'A recipe')->where('activeCategory', 'Recipes')->has('categories', 2));

    $this->get('/blog?category=Nonsense')->assertInertia(fn ($page) => $page->has('posts', 2)->where('activeCategory', null));
});

it('paginates with working previous and next links and a canonical per page', function (): void {
    BlogPost::factory()->count(11)->sequence(fn ($sequence) => ['published_at' => now()->subHours($sequence->index + 1)])->create();

    $this->get('/blog')->assertInertia(fn ($page) => $page
        ->has('posts', 9)->where('pagination.last', 2)->where('pagination.prev', null)->where('pagination.next', route('web.blog.index', ['page' => 2]))
        ->where('seo.canonical', route('web.blog.index')));

    $this->get('/blog?page=2')->assertInertia(fn ($page) => $page
        ->has('posts', 2)->where('pagination.prev', route('web.blog.index'))->where('pagination.next', null)
        ->where('seo.canonical', route('web.blog.index', ['page' => 2])));
});

it('publishes each article with article structured data and an article social preview', function (): void {
    $post = BlogPost::factory()->create([
        'title' => 'How we make our biryani',
        'slug' => 'how-we-make-our-biryani',
        'excerpt' => 'A look at the slow cooked rice and spices.',
        'category' => 'Recipes',
        'cover_path' => 'blog/cover.jpg',
    ]);

    $response = $this->get('/blog/how-we-make-our-biryani')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/blog/Show')
            ->where('post.title', 'How we make our biryani')
            ->where('seo.type', 'article')
            ->where('seo.canonical', route('web.blog.show', ['slug' => 'how-we-make-our-biryani']))
            ->where('seo.image', url('/storage/blog/cover.jpg')));

    $article = collect(blogGraph($response->getContent()))->firstWhere('@type', 'BlogPosting');

    expect($article['headline'])->toBe('How we make our biryani')
        ->and($article['description'])->toBe('A look at the slow cooked rice and spices.')
        ->and($article['image'])->toBe([url('/storage/blog/cover.jpg')])
        ->and($article['datePublished'])->toBe($post->published_at->toIso8601String())
        ->and($article['author']['name'])->toBe(config('app.name'))
        ->and($article['publisher']['logo']['url'])->toBe(Brand::logo())
        ->and($article['articleSection'])->toBe('Recipes');

    expect($response->getContent())->toContain('property="og:type" content="article"')
        ->and(collect(blogGraph($response->getContent()))->pluck('@type'))->toContain('BreadcrumbList');
});

it('hides drafts, scheduled posts and unknown addresses', function (): void {
    BlogPost::factory()->draft()->create(['slug' => 'my-draft']);
    BlogPost::factory()->scheduled()->create(['slug' => 'later-post']);

    $this->get('/blog/my-draft')->assertNotFound();
    $this->get('/blog/later-post')->assertNotFound();
    $this->get('/blog/no-such-post')->assertNotFound();
});

it('shows the Bangla text in Bangla and falls back to English when it is missing', function (): void {
    BlogPost::factory()->create(['slug' => 'bilingual', 'title' => 'English title', 'title_bn' => 'বাংলা শিরোনাম', 'excerpt' => 'English excerpt', 'excerpt_bn' => null, 'body' => 'English body', 'body_bn' => 'বাংলা লেখা']);

    $this->withSession(['locale' => 'bn'])->get('/blog/bilingual')
        ->assertInertia(fn ($page) => $page
            ->where('post.title', 'বাংলা শিরোনাম')
            ->where('post.excerpt', 'English excerpt')
            ->where('post.html', "<p>বাংলা লেখা</p>\n"));

    $this->withSession(['locale' => 'en'])->get('/blog/bilingual')->assertInertia(fn ($page) => $page->where('post.title', 'English title'));
});

it('offers related posts from the same category first and never the post itself', function (): void {
    $post = BlogPost::factory()->create(['slug' => 'main', 'category' => 'Recipes', 'published_at' => now()->subDays(10)]);
    $sameCategory = BlogPost::factory()->create(['title' => 'Same category', 'category' => 'Recipes', 'published_at' => now()->subDays(9)]);
    BlogPost::factory()->count(3)->create(['category' => 'News', 'published_at' => now()->subHour()]);

    $this->get('/blog/main')->assertInertia(fn ($page) => $page
        ->has('related', 3)->where('related.0.title', 'Same category'));

    expect($sameCategory->id)->not->toBe($post->id);
});

it('renders markdown but removes anything that could run code or load outside content', function (): void {
    BlogPost::factory()->create(['slug' => 'markdown', 'body' => implode("\n\n", [
        '## A heading',
        'Some **bold** and *italic* text.',
        "- one\n- two",
        '<script>alert(1)</script>',
        '<img src="x" onerror="alert(2)">',
        '[bad link](javascript:alert(3))',
        '[good link](https://example.com/page)',
        '![tracking pixel](https://evil.example/pixel.png)',
    ])]);

    $html = $this->get('/blog/markdown')->viewData('page')['props']['post']['html'];

    expect($html)->toContain('<h2>A heading</h2>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<li>one</li>')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<img')
        ->not->toContain('evil.example/pixel.png')
        ->toContain('href="https://example.com/page"')
        ->toContain('rel="nofollow noopener noreferrer"')
        ->toContain('target="_blank"');
});

it('estimates reading time from the word count', function (): void {
    $short = BlogPost::factory()->make(['body' => 'just a few words']);
    $long = BlogPost::factory()->make(['body' => implode(' ', array_fill(0, 650, 'word'))]);

    expect($short->readingMinutes('en'))->toBe(1)->and($long->readingMinutes('en'))->toBe(4);
});

it('serves an RSS feed of published posts only', function (): void {
    BlogPost::factory()->create(['title' => 'Feed post', 'slug' => 'feed-post', 'excerpt' => 'Fish & chips', 'category' => 'News']);
    BlogPost::factory()->draft()->create(['title' => 'Hidden draft']);

    $response = $this->get('/blog/feed.xml')->assertOk();
    $xml = simplexml_load_string($response->getContent());

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml')
        ->and((string) $xml->channel->item[0]->title)->toBe('Feed post')
        ->and((string) $xml->channel->item[0]->link)->toBe(route('web.blog.show', ['slug' => 'feed-post']))
        ->and((string) $xml->channel->item[0]->description)->toBe('Fish & chips')
        ->and(count($xml->channel->item))->toBe(1);

    expect($this->get('/')->getContent())->toContain('type="application/rss+xml"');
});

it('lists the blog and its published posts in the sitemap', function (): void {
    BlogPost::factory()->create(['slug' => 'visible-post']);
    BlogPost::factory()->draft()->create(['slug' => 'draft-post']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.route('web.blog.index').'</loc>')
        ->toContain('<loc>'.route('web.blog.show', ['slug' => 'visible-post']).'</loc>')
        ->not->toContain('draft-post');
});

it('shows the three latest posts on the home page', function (): void {
    BlogPost::factory()->count(5)->sequence(fn ($sequence) => ['title' => 'Post '.$sequence->index, 'published_at' => now()->subHours($sequence->index + 1)])->create();
    BlogPost::factory()->draft()->create();

    $this->get('/')->assertInertia(fn ($page) => $page->has('posts', 3)->where('posts.0.title', 'Post 0'));
});

it('lets an owner write, publish, schedule and delete posts', function (): void {
    $this->actingAs($this->owner, 'staff')->get('/admin/blog')->assertOk()->assertInertia(fn ($page) => $page->component('admin/blog/Index'));

    $payload = ['title' => 'Our new menu', 'excerpt' => 'Meet the new dishes.', 'body' => "## Welcome\n\nHello.", 'status' => 'published'];

    $this->actingAs($this->owner, 'staff')->post('/admin/blog', $payload)->assertSessionHasNoErrors()->assertSessionHas('success', __('blog.published'));

    $post = BlogPost::query()->where('title', 'Our new menu')->firstOrFail();

    expect($post->slug)->toBe('our-new-menu')
        ->and($post->author_id)->toBe($this->owner->id)
        ->and($post->isPublic())->toBeTrue();

    // A second post with the same title gets its own address.
    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$payload, 'status' => 'draft']);
    expect(BlogPost::query()->where('title', 'Our new menu')->orderByDesc('id')->first()->slug)->toBe('our-new-menu-2');

    $this->actingAs($this->owner, 'staff')->put('/admin/blog/'.$post->id, [...$payload, 'status' => 'published', 'published_at' => now()->addWeek()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
    expect($post->fresh()->isPublic())->toBeFalse();
    $this->get('/blog/our-new-menu')->assertNotFound();

    $this->actingAs($this->owner, 'staff')->put('/admin/blog/'.$post->id, [...$payload, 'status' => 'draft']);
    expect($post->fresh()->published_at)->toBeNull();

    $this->actingAs($this->owner, 'staff')->delete('/admin/blog/'.$post->id)->assertSessionHas('success', __('blog.deleted'));
    expect(BlogPost::query()->find($post->id))->toBeNull();
});

it('stores, replaces and removes the cover photo', function (): void {
    $base = ['title' => 'Cover test', 'excerpt' => 'x', 'body' => 'y', 'status' => 'published'];

    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$base, 'cover' => UploadedFile::fake()->image('a.jpg', 1200, 675)])->assertSessionHasNoErrors();

    $post = BlogPost::query()->firstOrFail();
    Storage::disk('public')->assertExists($post->cover_path);
    $first = $post->cover_path;

    // Multipart updates arrive as a POST that Laravel treats as PUT.
    $this->actingAs($this->owner, 'staff')->post('/admin/blog/'.$post->id, [...$base, '_method' => 'put', 'cover' => UploadedFile::fake()->image('b.png', 1200, 675)])->assertSessionHasNoErrors();

    $second = $post->fresh()->cover_path;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first)->assertExists($second);

    $this->actingAs($this->owner, 'staff')->post('/admin/blog/'.$post->id, [...$base, '_method' => 'put', 'remove_cover' => '1'])->assertSessionHasNoErrors();
    expect($post->fresh()->cover_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('rejects invalid posts with clear messages', function (): void {
    BlogPost::factory()->create(['slug' => 'taken']);
    $base = ['title' => 'T', 'excerpt' => 'x', 'body' => 'y', 'status' => 'draft'];

    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [])->assertSessionHasErrors(['title', 'excerpt', 'body', 'status']);
    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$base, 'slug' => 'Bad Slug!'])->assertSessionHasErrors(['slug' => __('blog.slug_format')]);
    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$base, 'slug' => 'taken'])->assertSessionHasErrors(['slug' => __('blog.slug_taken')]);
    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$base, 'status' => 'archived'])->assertSessionHasErrors('status');
    $this->actingAs($this->owner, 'staff')->post('/admin/blog', [...$base, 'cover' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('cover');
});

it('keeps the blog manager away from other roles and visitors', function (): void {
    $manager = User::factory()->role('branch-manager')->create();
    $post = BlogPost::factory()->create();

    $this->actingAs($manager, 'staff')->get('/admin/blog')->assertForbidden();
    $this->actingAs($manager, 'staff')->post('/admin/blog', ['title' => 'x'])->assertForbidden();
    $this->actingAs($manager, 'staff')->delete('/admin/blog/'.$post->id)->assertForbidden();
});

it('sends guests to sign in', function (): void {
    $this->get('/admin/blog')->assertRedirect();
});
