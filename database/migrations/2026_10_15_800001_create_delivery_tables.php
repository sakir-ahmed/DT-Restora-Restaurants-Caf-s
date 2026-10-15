<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vehicle', 30)->default('motorcycle');
            $table->string('plate', 30)->nullable();
            $table->boolean('is_on_duty')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending | assigned | picked_up | on_the_way | delivered | failed
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->timestamp('cod_received_at')->nullable();
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['status']);
        });

        Schema::create('rider_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['rider_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_locations');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('riders');
    }
};
