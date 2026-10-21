<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create(['branch_id' => $this->branch->id]);
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create(['branch_id' => $this->branch->id, 'menu_category_id' => $category->id, 'base_price' => '200.00']);
    $this->rice = Ingredient::factory()->create();
    $this->beef = Ingredient::factory()->create();
});

it('saves a recipe, rejects a repeated ingredient, and removes it when emptied', function (): void {
    $this->actingAs($this->owner, 'staff')->post(route('admin.recipes.store'), [
        'menu_item_id' => $this->item->id,
        'lines' => [['ingredient_id' => $this->rice->id, 'qty' => '0.2'], ['ingredient_id' => $this->beef->id, 'qty' => '0.1']],
    ])->assertSessionHasNoErrors();

    expect(Recipe::query()->where('menu_item_id', $this->item->id)->first()->items)->toHaveCount(2);

    $this->actingAs($this->owner, 'staff')->post(route('admin.recipes.store'), [
        'menu_item_id' => $this->item->id,
        'lines' => [['ingredient_id' => $this->rice->id, 'qty' => '1'], ['ingredient_id' => $this->rice->id, 'qty' => '2']],
    ])->assertSessionHasErrors();

    $this->actingAs($this->owner, 'staff')->post(route('admin.recipes.store'), [
        'menu_item_id' => $this->item->id,
        'lines' => [],
    ])->assertSessionHasNoErrors();

    expect(Recipe::query()->where('menu_item_id', $this->item->id)->exists())->toBeFalse();
});

it('shows the recipes page', function (): void {
    $this->actingAs($this->owner, 'staff')->get(route('admin.recipes.index'))->assertOk();
});
