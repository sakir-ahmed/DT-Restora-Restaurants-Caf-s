<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('mobile', 20)->index();
            $table->string('locale', 5)->default('en');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('device', 10)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('stage', 20)->default('visited');
            $table->unsignedTinyInteger('stage_rank')->default(0)->index();
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->boolean('consented')->default(true);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('demo_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demo_session_id')->constrained('demo_sessions')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 120)->nullable();
            $table->string('path', 200)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['demo_session_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_events');
        Schema::dropIfExists('demo_sessions');
    }
};
