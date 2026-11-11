<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the kitchen has an order, apart from whether it is paid. Paying used to overwrite the
     * order's status, so an order paid first lost its kitchen step. Now both are kept.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('kitchen_status', 20)->default('pending')->after('status')->index();
        });

        // Orders already in the kitchen flow keep their step. Paid orders are taken as served.
        DB::table('orders')->whereIn('status', ['pending', 'preparing', 'ready', 'served'])->update(['kitchen_status' => DB::raw('status')]);
        DB::table('orders')->whereIn('status', ['paid', 'void'])->update(['kitchen_status' => 'served']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('kitchen_status');
        });
    }
};
