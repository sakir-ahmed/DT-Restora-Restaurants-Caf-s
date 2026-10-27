<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['menu_items', 'menu_item_variants', 'menu_item_addons'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedInteger('menu_no')->nullable()->unique();
            });
        }

        $number = 0;

        foreach (DB::table('menu_items')->orderBy('id')->pluck('id') as $itemId) {
            DB::table('menu_items')->where('id', $itemId)->update(['menu_no' => ++$number]);

            foreach (['menu_item_variants', 'menu_item_addons'] as $table) {
                foreach (DB::table($table)->where('menu_item_id', $itemId)->orderBy('id')->pluck('id') as $id) {
                    DB::table($table)->where('id', $id)->update(['menu_no' => ++$number]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['menu_no']);
                $blueprint->dropColumn('menu_no');
            });
        }
    }
};
