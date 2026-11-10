<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            // Delete confirmations keep their button locked for this many seconds when the delay is on.
            $table->boolean('delete_confirm_delay_enabled')->default(false);
            $table->unsignedSmallInteger('delete_confirm_delay_seconds')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn(['delete_confirm_delay_enabled', 'delete_confirm_delay_seconds']);
        });
    }
};
