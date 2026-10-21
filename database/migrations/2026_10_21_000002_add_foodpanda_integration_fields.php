<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aggregator_channels', function (Blueprint $table): void {
            $table->string('environment', 12)->default('sandbox')->after('commission_percent');
            $table->string('chain_id', 64)->nullable()->after('environment');
            $table->string('client_id', 128)->nullable()->after('chain_id');
            $table->text('client_secret')->nullable()->after('client_id');
            $table->json('vendor_map')->nullable()->after('client_secret');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('aggregator_sku', 64)->nullable()->after('barcode');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->json('aggregator_meta')->nullable()->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('aggregator_meta');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('aggregator_sku');
        });

        Schema::table('aggregator_channels', function (Blueprint $table): void {
            $table->dropColumn(['environment', 'chain_id', 'client_id', 'client_secret', 'vendor_map', 'last_synced_at', 'last_error']);
        });
    }
};
