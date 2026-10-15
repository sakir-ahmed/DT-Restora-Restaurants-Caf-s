<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->string('invoice_no')->nullable();
            $table->date('purchased_at');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft | received | cancelled
            $table->timestamp('received_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('qty', 18, 3);
            $table->decimal('unit_cost', 12, 4); // per purchase unit
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained();
            $table->decimal('qty', 18, 6); // base units per single serving
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
