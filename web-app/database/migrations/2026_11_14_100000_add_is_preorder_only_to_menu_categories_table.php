<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            // A pre-order only category: shown in the menu book after the Saudi Arabian introduction page, never sold through the POS, website or table menu.
            $table->boolean('is_preorder_only')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table): void {
            $table->dropColumn('is_preorder_only');
        });
    }
};
