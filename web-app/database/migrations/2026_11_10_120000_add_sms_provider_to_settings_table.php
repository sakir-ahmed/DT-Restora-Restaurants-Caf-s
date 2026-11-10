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
            // Which SMS provider sends messages, and that provider's credentials
            // (an encrypted JSON object, keyed by provider, so a switch keeps old tokens).
            $table->string('sms_provider', 40)->nullable();
            $table->text('sms_config')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn(['sms_provider', 'sms_config']);
        });
    }
};
