<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A category is optional: an ingredient without one is simply uncategorised.
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->foreignId('ingredient_category_id')->nullable()->after('unit_id')
                ->constrained('ingredient_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ingredient_category_id');
        });

        Schema::dropIfExists('ingredient_categories');
    }
};
