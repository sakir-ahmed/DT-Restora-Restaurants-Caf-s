<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Website delivery is switched on or off per branch, next to the
     * branch's own delivery areas. Every branch starts with the value the
     * shared setting had, so nothing changes until the owner edits it.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('online_delivery_enabled')->default(true)->after('online_ordering_enabled');
        });

        $global = DB::table('settings')->value('online_delivery_enabled');

        if ($global !== null) {
            DB::table('branches')->update(['online_delivery_enabled' => (bool) $global]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('online_delivery_enabled');
        });
    }
};
