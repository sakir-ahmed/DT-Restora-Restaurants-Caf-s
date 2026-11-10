<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys that are joined or filtered on in hot paths but had no index.
     *
     * @var array<string, list<string>>
     */
    private array $indexes = [
        'order_items' => ['order_id', 'menu_item_id'],
        'orders' => ['restaurant_table_id', 'shift_id'],
        'journal_lines' => ['journal_id'],
        'purchase_items' => ['purchase_id', 'ingredient_id'],
        'stock_transfer_items' => ['stock_transfer_id'],
        'stock_movements' => ['ingredient_id'],
        'ingredient_stocks' => ['ingredient_id'],
        'recipe_items' => ['ingredient_id'],
        'loyalty_entries' => ['order_id'],
        'void_requests' => ['order_id'],
        'menu_items' => ['menu_category_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
