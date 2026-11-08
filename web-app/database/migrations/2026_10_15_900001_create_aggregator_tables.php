<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregator_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique(); // foodpanda | pathao_food | manual | ...
            $table->string('name');
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('aggregator_channel_id')->nullable()->constrained('aggregator_channels')->nullOnDelete();
            $table->string('aggregator_order_id', 100)->nullable();
            $table->decimal('commission_amount', 12, 2)->default(0);
        });

        Schema::create('aggregator_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aggregator_channel_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->string('status', 20)->default('pending'); // pending | reconciled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregator_payouts');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['aggregator_channel_id', 'aggregator_order_id', 'commission_amount']);
        });

        Schema::dropIfExists('aggregator_channels');
    }
};
