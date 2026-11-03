<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Purchase;
use App\Models\Supplier;
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

it('links ingredients to a supplier when creating and editing', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $oil = Ingredient::factory()->create(['unit_id' => $this->unit->id]);

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/suppliers', ['name' => 'Fresh Co', 'is_active' => true, 'ingredient_ids' => [$rice->id, $oil->id]])
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->where('name', 'Fresh Co')->firstOrFail();

    expect($supplier->ingredients()->pluck('ingredients.id')->all())->toEqualCanonicalizing([$rice->id, $oil->id]);

    $this->actingAs($this->owner, 'staff')
        ->put("/admin/suppliers/{$supplier->id}", ['name' => 'Fresh Co', 'is_active' => true, 'ingredient_ids' => [$oil->id]])
        ->assertSessionHasNoErrors();

    expect($supplier->ingredients()->pluck('ingredients.id')->all())->toBe([$oil->id]);
});

it('blocks deleting a supplier with purchases and names the related data', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'Fresh Co']);

    Purchase::query()->create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $supplier->id,
        'purchased_at' => now()->toDateString(),
        'status' => Purchase::STATUS_DRAFT,
    ]);

    $this->actingAs($this->owner, 'staff')
        ->delete("/admin/suppliers/{$supplier->id}")
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Fresh Co') && str_contains($message, '1 purchases'));

    expect(Supplier::query()->count())->toBe(1);
});

it('deletes a supplier without purchases and drops its ingredient links', function (): void {
    $ingredient = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $supplier = Supplier::factory()->create();
    $supplier->ingredients()->attach($ingredient);

    $this->actingAs($this->owner, 'staff')
        ->delete("/admin/suppliers/{$supplier->id}")
        ->assertSessionHas('success');

    expect(Supplier::query()->count())->toBe(0)
        ->and(Ingredient::query()->count())->toBe(1);
});

it('splits one purchase form into one purchase per supplier', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $oil = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $line = fn (Ingredient $ingredient, Supplier $supplier): array => [
        'ingredient_id' => $ingredient->id,
        'supplier_id' => $supplier->id,
        'unit_id' => $this->unit->id,
        'qty' => '2',
        'unit_cost' => '100',
    ];

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/purchases', [
            'purchased_at' => now()->toDateString(),
            'receive' => true,
            'items' => [$line($rice, $first), $line($oil, $second), $line($oil, $first)],
        ])
        ->assertSessionHasNoErrors();

    expect(Purchase::query()->count())->toBe(2)
        ->and(Purchase::query()->where('supplier_id', $first->id)->firstOrFail()->items()->count())->toBe(2)
        ->and(Purchase::query()->where('status', Purchase::STATUS_RECEIVED)->count())->toBe(2);
});

it('still accepts a single header supplier for every line', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/purchases', [
            'supplier_id' => $supplier->id,
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $rice->id, 'unit_id' => $this->unit->id, 'qty' => '1', 'unit_cost' => '50']],
        ])
        ->assertSessionHasNoErrors();

    expect(Purchase::query()->where('supplier_id', $supplier->id)->count())->toBe(1);
});

it('requires a supplier on each line when no header supplier is given', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/purchases', [
            'purchased_at' => now()->toDateString(),
            'items' => [['ingredient_id' => $rice->id, 'unit_id' => $this->unit->id, 'qty' => '1', 'unit_cost' => '50']],
        ])
        ->assertSessionHasErrors('items.0.supplier_id');
});

it('explains why an ingredient category with ingredients cannot be deleted', function (): void {
    $category = IngredientCategory::query()->create(['name' => 'Food', 'sort_order' => 1, 'is_active' => true]);
    Ingredient::factory()->create(['unit_id' => $this->unit->id, 'ingredient_category_id' => $category->id]);

    $this->actingAs($this->owner, 'staff')
        ->delete("/admin/ingredient-categories/{$category->id}")
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Food') && str_contains($message, '1 ingredients'));
});

it('lets many suppliers provide the same ingredient and one supplier provide many', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $oil = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $this->actingAs($this->owner, 'staff')
        ->put("/admin/suppliers/{$first->id}", ['name' => $first->name, 'is_active' => true, 'ingredient_ids' => [$rice->id, $oil->id]])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner, 'staff')
        ->put("/admin/suppliers/{$second->id}", ['name' => $second->name, 'is_active' => true, 'ingredient_ids' => [$rice->id]])
        ->assertSessionHasNoErrors();

    expect($rice->suppliers()->pluck('suppliers.id')->all())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($first->ingredients()->count())->toBe(2);
});

it('keeps an optional invoice number per line and splits purchases by supplier and invoice', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $line = fn (Supplier $supplier, ?string $invoice): array => [
        'ingredient_id' => $rice->id,
        'supplier_id' => $supplier->id,
        'invoice_no' => $invoice,
        'unit_id' => $this->unit->id,
        'qty' => '1',
        'unit_cost' => '10',
    ];

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/purchases', [
            'purchased_at' => now()->toDateString(),
            'items' => [$line($first, 'INV-1'), $line($first, 'INV-1'), $line($first, 'INV-2'), $line($second, null)],
        ])
        ->assertSessionHasNoErrors();

    expect(Purchase::query()->count())->toBe(3)
        ->and(Purchase::query()->where('invoice_no', 'INV-1')->firstOrFail()->items()->count())->toBe(2)
        ->and(Purchase::query()->where('supplier_id', $second->id)->firstOrFail()->invoice_no)->toBeNull();
});

it('saves one note on every purchase created from the form', function (): void {
    $rice = Ingredient::factory()->create(['unit_id' => $this->unit->id]);
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $line = fn (Supplier $supplier): array => [
        'ingredient_id' => $rice->id,
        'supplier_id' => $supplier->id,
        'unit_id' => $this->unit->id,
        'qty' => '1',
        'unit_cost' => '10',
    ];

    $this->actingAs($this->owner, 'staff')
        ->post('/admin/purchases', [
            'purchased_at' => now()->toDateString(),
            'note' => 'Weekly market run',
            'items' => [$line($first), $line($second)],
        ])
        ->assertSessionHasNoErrors();

    expect(Purchase::query()->pluck('note')->all())->toBe(['Weekly market run', 'Weekly market run']);
});
