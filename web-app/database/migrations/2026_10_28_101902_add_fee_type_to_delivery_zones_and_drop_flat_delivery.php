<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery areas carry a Bangla name, a fee type (fixed, or flexible and confirmed by phone) and an order.
        Schema::table('delivery_zones', function (Blueprint $table): void {
            $table->string('name_bn', 120)->nullable()->after('name');
            $table->string('fee_type', 10)->default('fixed')->after('fee');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });

        // Delivery is always limited to the areas the owner lists, so the "any address, one flat fee" mode is gone.
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn(['delivery_mode', 'delivery_flat_fee', 'delivery_min_order', 'delivery_eta_minutes']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->string('delivery_mode', 20)->default('zones')->after('online_delivery_enabled');
            $table->decimal('delivery_flat_fee', 10, 2)->default(0)->after('delivery_mode');
            $table->decimal('delivery_min_order', 10, 2)->default(0)->after('delivery_flat_fee');
            $table->unsignedSmallInteger('delivery_eta_minutes')->nullable()->after('delivery_min_order');
        });

        Schema::table('delivery_zones', function (Blueprint $table): void {
            $table->dropColumn(['name_bn', 'fee_type', 'sort_order']);
        });
    }
};
