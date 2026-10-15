<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->text('delivery_address')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['delivery_zone_id', 'delivery_address', 'scheduled_for', 'tracking_token']);
        });
    }
};
