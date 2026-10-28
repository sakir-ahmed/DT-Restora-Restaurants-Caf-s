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
        Schema::table('settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('established_year')->nullable()->after('tax_registration_no');
            $table->boolean('website_enabled')->default(true)->after('established_year');
            $table->boolean('online_delivery_enabled')->default(true)->after('website_enabled');
            $table->string('delivery_mode', 20)->default('zones')->after('online_delivery_enabled');
            $table->decimal('delivery_flat_fee', 10, 2)->default(0)->after('delivery_mode');
            $table->decimal('delivery_min_order', 10, 2)->default(0)->after('delivery_flat_fee');
            $table->unsignedSmallInteger('delivery_eta_minutes')->nullable()->after('delivery_min_order');
        });

        // Installs that used the BRAND_ESTABLISHED_YEAR variable keep its value; it moved to Settings.
        $year = env('BRAND_ESTABLISHED_YEAR');

        if (is_numeric($year)) {
            DB::table('settings')->update(['established_year' => (int) $year]);
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn([
                'established_year', 'website_enabled', 'online_delivery_enabled',
                'delivery_mode', 'delivery_flat_fee', 'delivery_min_order', 'delivery_eta_minutes',
            ]);
        });
    }
};
