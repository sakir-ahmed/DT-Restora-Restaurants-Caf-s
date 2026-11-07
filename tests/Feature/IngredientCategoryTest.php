<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->unit = Unit::query()->where('symbol', 'kg')->firstOrFail();
});

it('lets an owner add, rename and delete an ingredient category', function (): void {
    $this->actingAs($this->owner, 'staff')
        ->post('/admin/ingredient-categories', ['name' => 'Food', 'name_bn' => 'খাবার', 'sort_order' => 2, 'is_active' => true])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $category = IngredientCategory::query()->where('name', 'Food')->firstOrFail();

    expect($category->sort_order)->toBe(2);

    $this->actingAs($this->owner, 'staff')
        ->put("/admin/ingredient-categories/{$category->id}", ['name' => 'Packaging', 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Packaging')
        ->and($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->owner, 'staff')->delete("/admin/ingredient-categories/{$category->id}")->assertSessionHas('success');

    expect(IngredientCategory::query()->count())->toBe(0);
});

it('rejects a duplicate category name but lets a category keep its own name', function (): void {
    $food = IngredientCategory::factory()->create(['name' => 'Food']);

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/ingredient-categories', ['name' => 'Food'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->owner, 'staff')
        ->put("/admin/ingredient-categories/{$food->id}", ['name' => 'Food', 'name_bn' => 'খাবার'])
        ->assertSessionHasNoErrors();
});

it('keeps a category that still has ingredients', function (): void {
    $category = IngredientCategory::factory()->create();
    Ingredient::factory()->create(['unit_id' => $this->unit->id, 'ingredient_category_id' => $category->id]);

    $this->actingAs($this->owner, 'staff')
        ->delete("/admin/ingredient-categories/{$category->id}")
        ->assertSessionHas('error');

    expect(IngredientCategory::query()->count())->toBe(1);
});

it('treats the category of an ingredient as optional', function (): void {
    $category = IngredientCategory::factory()->create();

    $this->actingAs($this->owner, 'staff')->post('/admin/ingredients', [
        'name' => 'Rice', 'unit_id' => $this->unit->id, 'ingredient_category_id' => $category->id, 'reorder_level' => 5, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->owner, 'staff')->post('/admin/ingredients', [
        'name' => 'Salt', 'unit_id' => $this->unit->id, 'ingredient_category_id' => '', 'reorder_level' => 1, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(Ingredient::query()->where('name', 'Rice')->value('ingredient_category_id'))->toBe($category->id)
        ->and(Ingredient::query()->where('name', 'Salt')->value('ingredient_category_id'))->toBeNull();

    $this->actingAs($this->owner, 'staff')->post('/admin/ingredients', [
        'name' => 'Box', 'unit_id' => $this->unit->id, 'ingredient_category_id' => 9999, 'is_active' => true,
    ])->assertSessionHasErrors('ingredient_category_id');
});

it('takes an ingredient out of its category when the ingredient is edited to none', function (): void {
    $category = IngredientCategory::factory()->create();
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->unit->id, 'ingredient_category_id' => $category->id]);

    $this->actingAs($this->owner, 'staff')->put("/admin/ingredients/{$ingredient->id}", [
        'name' => $ingredient->name, 'unit_id' => $this->unit->id, 'ingredient_category_id' => '', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($ingredient->fresh()->ingredient_category_id)->toBeNull();
});

it('gives the purchase page the active categories and each ingredient category', function (): void {
    $food = IngredientCategory::factory()->create(['name' => 'Food', 'sort_order' => 1]);
    IngredientCategory::factory()->create(['name' => 'Retired', 'is_active' => false]);
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id, 'ingredient_category_id' => $food->id]);
    $salt = Ingredient::factory()->create(['unit_id' => $this->unit->id, 'ingredient_category_id' => null]);

    $this->actingAs($this->owner, 'staff')->get('/admin/purchases')->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.name', 'Food')
        ->where('ingredients', fn ($ingredients) => collect($ingredients)->firstWhere('id', $rice->id)['ingredient_category_id'] === $food->id
            && collect($ingredients)->firstWhere('id', $salt->id)['ingredient_category_id'] === null));
});

it('does not let a cashier manage ingredient categories', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->post('/admin/ingredient-categories', ['name' => 'Food'])->assertForbidden();

    expect(IngredientCategory::query()->count())->toBe(0);
});

it('opens the ingredients page filtered to low stock from the dashboard link', function (): void {
    $this->actingAs($this->owner, 'staff')->get(route('admin.ingredients.index', ['low' => 1]))
        ->assertInertia(fn ($page) => $page->where('lowOnly', true));

    $this->actingAs($this->owner, 'staff')->get(route('admin.ingredients.index'))
        ->assertInertia(fn ($page) => $page->where('lowOnly', false));
});
