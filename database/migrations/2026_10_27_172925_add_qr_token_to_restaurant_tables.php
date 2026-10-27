<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->string('qr_token', 40)->nullable()->unique()->after('name');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_token');
        });

        // Every existing table gets its own secret; the old guessable /q/T1 links stop working.
        DB::table('restaurant_tables')->orderBy('id')->each(function (object $row): void {
            DB::table('restaurant_tables')->where('id', $row->id)->update([
                'qr_token' => Str::random(32),
                'qr_generated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['qr_token', 'qr_generated_at']);
        });
    }
};
