<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);
    Storage::fake('public');

    $this->branch = Branch::factory()->create(['name' => 'Gulshan Kitchen', 'code' => 'BR01', 'online_ordering_enabled' => true]);
    $this->otherBranch = Branch::factory()->create(['name' => 'Dhanmondi Cafe', 'code' => 'BR02']);

    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'name' => 'Kacchi Biryani',
        'photo_path' => null,
    ]);

    $otherCategory = MenuCategory::factory()->create(['branch_id' => $this->otherBranch->id]);
    $this->otherItem = MenuItem::factory()->create(['branch_id' => $this->otherBranch->id, 'menu_category_id' => $otherCategory->id]);

    $this->owner = User::factory()->role('owner')->create();
});

it('accepts only safe video links and builds embeds', function (string $url, ?string $provider): void {
    expect(Media::parseVideo($url)['provider'] ?? null)->toBe($provider);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
    'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube'],
    'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'youtube'],
    'vimeo' => ['https://vimeo.com/76979871', 'vimeo'],
    'facebook video' => ['https://www.facebook.com/restaurant/videos/1234567890', 'facebook'],
    'direct mp4' => ['https://cdn.example.com/clip.mp4', 'file'],
    'plain http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', null],
    'unknown site' => ['https://evil.example.com/watch?v=dQw4w9WgXcQ', null],
    'javascript scheme' => ['javascript:alert(1)', null],
    'youtube without id' => ['https://www.youtube.com/watch', null],
    'facebook page' => ['https://www.facebook.com/restaurant', null],
    'web page not video' => ['https://example.com/page.html', null],
]);

it('embeds youtube without cookies and with a thumbnail', function (): void {
    $video = Media::parseVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($video['embed_url'])->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($video['thumbnail'])->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
});

it('lets an owner upload several photos for a dish', function (): void {
    $this->actingAs($this->owner, 'staff')->post('/admin/media', [
        'owner' => 'item:'.$this->item->id,
        'images' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.png', 800, 600)],
        'title' => 'Plated',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $media = Media::query()->where('mediable_type', 'menu_item')->where('mediable_id', $this->item->id)->ordered()->get();

    expect($media)->toHaveCount(2)
        ->and($media->pluck('sort_order')->all())->toBe([1, 2])
        ->and($media->first()->title)->toBe('Plated');

    foreach ($media as $entry) {
        Storage::disk('public')->assertExists($entry->path);
    }
});

it('adds a video link to an outlet and the general gallery', function (): void {
    $this->actingAs($this->owner, 'staff')->post('/admin/media', [
        'owner' => 'branch:'.$this->branch->id,
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'title' => 'Tour',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->owner, 'staff')->post('/admin/media', [
        'owner' => 'general',
        'video_url' => 'https://vimeo.com/76979871',
    ])->assertSessionHasNoErrors();

    expect(Media::query()->where('kind', 'video')->count())->toBe(2)
        ->and(Media::query()->whereNull('mediable_type')->count())->toBe(1);
});

it('rejects unsupported links, non images and empty submissions', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->post('/admin/media', ['owner' => 'general', 'video_url' => 'https://evil.example.com/watch?v=dQw4w9WgXcQ'])
        ->assertSessionHasErrors(['video_url' => __('media.video_unsupported')]);

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/media', ['owner' => 'general', 'images' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]])
        ->assertSessionHasErrors('images.0');

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/media', ['owner' => 'general'])
        ->assertSessionHasErrors(['images' => __('media.nothing_to_add')]);

    expect(Media::query()->count())->toBe(0);
});

it('limits staff without the permission', function (): void {
    $cashier = User::factory()->role('cashier')->create(['branch_id' => $this->branch->id]);

    $this->actingAs($cashier, 'staff')->get('/admin/media')->assertForbidden();
    $this->actingAs($cashier, 'staff')->post('/admin/media', ['owner' => 'general', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
});

it('keeps a branch manager inside their own outlet', function (): void {
    $manager = User::factory()->role('branch-manager')->create(['branch_id' => $this->branch->id]);

    $this->actingAs($manager, 'staff')->get('/admin/media?owner=item:'.$this->item->id)->assertOk();
    $this->actingAs($manager, 'staff')->get('/admin/media?owner=item:'.$this->otherItem->id)->assertForbidden();
    $this->actingAs($manager, 'staff')->get('/admin/media?owner=branch:'.$this->otherBranch->id)->assertForbidden();
    $this->actingAs($manager, 'staff')->get('/admin/media?owner=general')->assertForbidden();

    $this->actingAs($manager, 'staff')
        ->post('/admin/media', ['owner' => 'item:'.$this->otherItem->id, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertForbidden();

    $foreign = Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $this->otherBranch->id]);

    $this->actingAs($manager, 'staff')->delete('/admin/media/'.$foreign->id)->assertForbidden();
    expect(Media::query()->find($foreign->id))->not->toBeNull();
});

it('rejects malformed owners', function (): void {
    $this->actingAs($this->owner, 'staff')->get('/admin/media?owner=branch:abc')->assertNotFound();
    $this->actingAs($this->owner, 'staff')->get('/admin/media?owner=../../etc')->assertNotFound();
});

it('reorders, hides and deletes media', function (): void {
    $first = Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $this->branch->id, 'sort_order' => 1]);
    $second = Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $this->branch->id, 'sort_order' => 2]);
    Storage::disk('public')->put($second->path, 'x');

    $this->actingAs($this->owner, 'staff')->post("/admin/media/{$second->id}/move", ['direction' => 'up'])->assertSessionHasNoErrors();

    expect($second->fresh()->sort_order)->toBeLessThan($first->fresh()->sort_order);

    $this->actingAs($this->owner, 'staff')->put("/admin/media/{$first->id}", ['title' => 'Entrance', 'is_active' => false])->assertSessionHasNoErrors();

    expect($first->fresh()->is_active)->toBeFalse()->and($first->fresh()->title)->toBe('Entrance');

    $this->actingAs($this->owner, 'staff')->delete("/admin/media/{$second->id}")->assertSessionHas('success');

    expect(Media::query()->find($second->id))->toBeNull();
    Storage::disk('public')->assertMissing($second->path);
});

it('shows a dish gallery with photos and videos, hiding inactive items', function (): void {
    Media::factory()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'sort_order' => 1, 'title' => 'Plated']);
    Media::factory()->video()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'sort_order' => 2, 'title' => 'How we cook it']);
    Media::factory()->inactive()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'sort_order' => 3, 'title' => 'Hidden']);

    $this->get('/menu/br01/items/'.$this->item->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('gallery', 2)
            ->where('gallery.0.kind', 'image')
            ->where('gallery.1.kind', 'video')
            ->where('gallery.1.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->where('gallery.1.provider', 'youtube'));
});

it('uses the first uploaded photo as the dish picture on the menu', function (): void {
    $photo = Media::factory()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'sort_order' => 1]);

    $this->get('/menu/br01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('categories.0.items.0.cover_url', $photo->src()));
});

it('shows outlet photos on the branch page and in its schema', function (): void {
    $photo = Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $this->branch->id, 'sort_order' => 1]);
    Media::factory()->video()->create(['mediable_type' => 'branch', 'mediable_id' => $this->branch->id, 'sort_order' => 2]);

    $response = $this->get('/locations/gulshan-kitchen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('media', 2)->where('seo.image', url($photo->src())));

    expect($response->getContent())->toContain(json_encode(url($photo->src()), JSON_UNESCAPED_SLASHES));
});

it('builds the public gallery from general, outlet and dish media only', function (): void {
    Media::factory()->create(['mediable_type' => null, 'mediable_id' => null, 'title' => 'Opening day']);
    Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $this->branch->id, 'title' => 'Dining room']);
    Media::factory()->video()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'title' => 'Biryani video']);
    Media::factory()->inactive()->create(['title' => 'Hidden photo']);

    $closed = Branch::factory()->inactive()->create();
    Media::factory()->create(['mediable_type' => 'branch', 'mediable_id' => $closed->id, 'title' => 'Closed outlet']);

    $unavailable = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $this->item->menu_category_id, 'is_available' => false]);
    Media::factory()->create(['mediable_type' => 'menu_item', 'mediable_id' => $unavailable->id, 'title' => 'Sold out dish']);

    $response = $this->get('/gallery')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('web/Gallery')->has('entries', 3));

    $titles = collect($response->viewData('page')['props']['entries'])->pluck('title');

    expect($titles->all())->toBe(['Opening day', 'Dining room', 'Biryani video']);

    $types = collect(json_decode(
        html_entity_decode(preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $m) ? $m[1] : '{}'),
        true,
    )['@graph'] ?? [])->pluck('@type');

    expect($types)->toContain('ImageGallery', 'VideoObject', 'BreadcrumbList');
});

it('renders an empty gallery without errors and lists it in the sitemap', function (): void {
    $this->get('/gallery')->assertOk()->assertInertia(fn ($page) => $page->has('entries', 0));

    expect($this->get('/sitemap.xml')->getContent())->toContain('<loc>'.route('web.gallery').'</loc>');
});

it('previews recent photos on the home page', function (): void {
    Media::factory()->count(7)->create(['mediable_type' => null, 'mediable_id' => null]);
    Media::factory()->video()->create();

    $this->get('/')->assertInertia(fn ($page) => $page->has('gallery', 6));
});

it('gives the dish form every photo and video of the dish, hidden ones included', function (): void {
    Media::factory()->count(3)->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id]);
    Media::factory()->video()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id]);
    Media::factory()->inactive()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id]);

    $manager = User::factory()->role('branch-manager')->create(['branch_id' => $this->branch->id]);

    $this->actingAs($manager, 'staff')->get('/admin/menu')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/menu/Index')
            ->where('canViewMedia', true)
            ->where('canManageMedia', true)
            ->has('categories.0.items.0.media_gallery', 5));

    $cashier = User::factory()->role('cashier')->create(['branch_id' => $this->branch->id]);

    $this->actingAs($cashier, 'staff')->get('/admin/menu')
        ->assertInertia(fn ($page) => $page->where('canViewMedia', false)->where('canManageMedia', false));
});

it('serves photo addresses that work on any host or port while search data stays absolute', function (): void {
    $photo = Media::factory()->create(['mediable_type' => 'menu_item', 'mediable_id' => $this->item->id, 'path' => 'media/pic.jpg']);

    expect($photo->src())->toBe('/storage/media/pic.jpg')
        ->and($photo->absoluteSrc())->toBe(url('/storage/media/pic.jpg'));

    $response = $this->get('/menu/br01/items/'.$this->item->id)
        ->assertInertia(fn ($page) => $page
            ->where('gallery.0.src', '/storage/media/pic.jpg')
            ->where('gallery.0.absolute_src', url('/storage/media/pic.jpg'))
            ->where('seo.image', url('/storage/media/pic.jpg')));

    $dish = collect(jobGraphFromHtml($response->getContent()))->firstWhere('@type', 'MenuItem');

    expect($dish['image'])->toBe([url('/storage/media/pic.jpg')]);
});

/**
 * @return array<int, array<string, mixed>>
 */
function jobGraphFromHtml(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? '{}', true)['@graph'] ?? [];
}
