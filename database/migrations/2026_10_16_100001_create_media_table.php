<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            // Owner: a branch or menu item. Both null means the general gallery.
            $table->string('mediable_type', 40)->nullable();
            $table->unsignedBigInteger('mediable_id')->nullable();
            $table->string('kind', 10);
            $table->string('path')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('title', 150)->nullable();
            $table->string('title_bn', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'sort_order']);
            $table->index(['is_active', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
