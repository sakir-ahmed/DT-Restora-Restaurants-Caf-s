<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            // Standard units (g, kg, ml, l, pcs ...) are fixed; owners add their own beside them.
            $table->boolean('is_system')->default(false)->after('factor');
        });

        DB::table('units')
            ->whereIn('symbol', ['g', 'kg', 'mg', 'l', 'ml', 'tsp', 'tbsp', 'cup', 'pcs', 'dozen'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
