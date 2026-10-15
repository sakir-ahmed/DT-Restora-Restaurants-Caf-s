<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('symbol', 20)->unique();
            $table->string('type', 20); // weight | volume | count
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('factor', 18, 6)->default(1); // 1 unit = factor base units
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ingredients are global; stock and cost live per branch.
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->decimal('reorder_level', 18, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ingredient_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('stock_qty', 18, 3)->default(0);
            $table->decimal('avg_cost', 12, 4)->default(0); // per base unit
            $table->decimal('last_cost', 12, 4)->default(0); // last purchase cost per base unit
            $table->timestamps();

            $table->unique(['branch_id', 'ingredient_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 18, 3); // signed, base units
            $table->string('type', 30); // purchase | sale | wastage | adjustment | count | transfer_in | transfer_out
            $table->nullableMorphs('reference');
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('balance_after', 18, 3);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('ingredient_stocks');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('units');
    }
};
