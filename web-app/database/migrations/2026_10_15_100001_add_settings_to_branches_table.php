<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->decimal('vat_percent', 5, 2)->default(15.00)->after('longitude');
            $table->boolean('vat_inclusive')->default(false)->after('vat_percent');
            $table->decimal('service_charge_percent', 5, 2)->default(0)->after('vat_inclusive');
            $table->boolean('allow_negative_stock')->default(false)->after('service_charge_percent');
            $table->boolean('online_ordering_enabled')->default(false)->after('allow_negative_stock');
            $table->json('opening_hours')->nullable()->after('online_ordering_enabled');
            $table->string('receipt_footer')->nullable()->after('opening_hours');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn([
                'vat_percent', 'vat_inclusive', 'service_charge_percent',
                'allow_negative_stock', 'online_ordering_enabled',
                'opening_hours', 'receipt_footer',
            ]);
        });
    }
};
