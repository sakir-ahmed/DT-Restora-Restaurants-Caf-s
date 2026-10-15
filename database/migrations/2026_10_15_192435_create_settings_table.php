<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('discount_limit_percent', 5, 2)->default(10);
            $table->decimal('wastage_spike_multiplier', 5, 2)->default(1.5);
            $table->unsignedSmallInteger('pos_pin_minutes')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
