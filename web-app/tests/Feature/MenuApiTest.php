<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->base = '/api/v1/branches/'.$this->branch->id;
});

it('creates, edits and deletes a menu category, protecting one that has dishes', function (): void {
    $id = $this->withToken($this->token)->postJson($this->base.'/menu-categories', ['name' => 'Starters', 'is_active' => true])
        ->assertCreated()->json('data.id');

    $this->withToken($this->token)->putJson($this->base.'/menu-categories/'.$id, ['name' => 'Starters and snacks', 'sort_order' => 2])
        ->assertOk()->assertJsonPath('data.name', 'Starters and snacks');

    MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $id]);
    $this->withToken($this->token)->deleteJson($this->base.'/menu-categories/'.$id)->assertUnprocessable();

    expect($this->withToken($this->token)->getJson($this->base.'/menu-categories')->json('data.0.items_count'))->toBe(1);

    MenuItem::query()->where('menu_category_id', $id)->delete();
    $this->withToken($this->token)->deleteJson($this->base.'/menu-categories/'.$id)->assertOk();

    expect(MenuCategory::query()->count())->toBe(0);
});

it('deletes a dish', function (): void {
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id]);

    $this->withToken($this->token)->deleteJson($this->base.'/menu-items/'.$item->id)->assertOk();

    expect(MenuItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('replaces a dish photo and makes it the featured one', function (): void {
    Storage::fake('public');
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id]);

    $created = $this->withToken($this->token)->post('/api/v1/media/images', [
        'owner' => 'item:'.$item->id,
        'images' => [UploadedFile::fake()->image('a.jpg')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $mediaId = $created->json('data.0.id');

    $this->withToken($this->token)->post('/api/v1/media/'.$mediaId.'/replace', ['image' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])
        ->assertOk();

    $this->withToken($this->token)->postJson('/api/v1/media/'.$mediaId.'/feature')->assertOk()->assertJsonPath('data.is_featured', true);

    expect(Media::query()->find($mediaId)->is_featured)->toBeTrue();

    $items = $this->withToken($this->token)->getJson('/api/v1/media?owner=branch:'.$this->branch->id)->assertOk()->json('data.items');
    expect($items)->toHaveCount(1);
});

it('keeps menu management to people who may manage the menu', function (): void {
    $waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $token = $waiter->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->postJson($this->base.'/menu-categories', ['name' => 'X'])->assertForbidden();
});
