<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug', 160)->unique();
            $table->string('title', 180);
            $table->string('title_bn', 180)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('excerpt', 300);
            $table->string('excerpt_bn', 300)->nullable();
            $table->longText('body');
            $table->longText('body_bn')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_alt', 150)->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
