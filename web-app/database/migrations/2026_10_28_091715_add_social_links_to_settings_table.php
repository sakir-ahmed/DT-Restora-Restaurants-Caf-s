<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->string('social_facebook', 255)->nullable()->after('tax_registration_no');
            $table->string('social_instagram', 255)->nullable()->after('social_facebook');
            $table->string('social_youtube', 255)->nullable()->after('social_instagram');
            $table->string('social_tiktok', 255)->nullable()->after('social_youtube');
            $table->string('social_whatsapp', 255)->nullable()->after('social_tiktok');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn(['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp']);
        });
    }
};
