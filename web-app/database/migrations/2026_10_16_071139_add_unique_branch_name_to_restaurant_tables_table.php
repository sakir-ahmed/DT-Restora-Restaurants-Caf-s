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
        $hasDuplicate = DB::table('restaurant_tables')
            ->select('branch_id', 'name')
            ->groupBy('branch_id', 'name')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($hasDuplicate) {
            throw new RuntimeException('restaurant_tables already has two rows with the same name in one branch.');
        }

        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->unique(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'name']);
        });
    }
};
