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
            // Per-feature on/off overrides, e.g. {"invoice": true, "win_back": false}.
            $table->json('sms_features')->nullable();
        });

        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('feature', 40)->index();
            $table->string('mobile', 30)->index();
            $table->text('message');
            $table->string('provider', 40)->nullable();
            $table->string('status', 12)->index(); // sent | failed | skipped | demo
            $table->string('response', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('sms_features');
        });
    }
};
