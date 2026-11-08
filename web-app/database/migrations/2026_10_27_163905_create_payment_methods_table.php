<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('name_bn', 60)->nullable();
            $table->string('type', 10); // cash | wallet | card | other
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The four methods the POS already knew; code and type of these are fixed.
        $now = now();

        DB::table('payment_methods')->insert([
            ['code' => 'cash', 'name' => 'Cash', 'name_bn' => 'নগদ', 'type' => 'cash', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'bkash', 'name' => 'bKash', 'name_bn' => 'বিকাশ', 'type' => 'wallet', 'is_system' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'nagad', 'name' => 'Nagad', 'name_bn' => 'নগদ (মোবাইল)', 'type' => 'wallet', 'is_system' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'card', 'name' => 'Card / SSLCommerz', 'name_bn' => 'কার্ড / এসএসএলকমার্জ', 'type' => 'card', 'is_system' => true, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
