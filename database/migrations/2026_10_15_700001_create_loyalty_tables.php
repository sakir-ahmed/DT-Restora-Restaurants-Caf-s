<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('earn_rate')->default(1); // points per 100 BDT
            $table->decimal('point_value', 8, 2)->default(1.00); // 1 point = X BDT
            $table->unsignedInteger('expiry_days')->default(365);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        // Immutable points ledger: earned (+), redeemed (-), expired (-), adjusted (+/-).
        Schema::create('loyalty_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('points'); // signed
            $table->integer('balance_after');
            $table->timestamp('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['expires_at']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10); // percent | fixed
            $table->decimal('value', 12, 2);
            $table->decimal('min_order', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->json('channels')->nullable(); // null = all channels
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id']);
            $table->index(['coupon_id', 'customer_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('points_redeemed')->default(0);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->date('birthday')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('birthday');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['coupon_id', 'points_redeemed']);
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('loyalty_entries');
        Schema::dropIfExists('loyalty_settings');
    }
};
