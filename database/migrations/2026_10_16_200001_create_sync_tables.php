<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every offline write gets a row here, keyed by the device's
        // client_id (ULID) so retries are idempotent.
        Schema::create('sync_items', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id', 40)->unique();
            $table->string('type', 30); // order | payment | shift | wastage | stock_count | attendance
            $table->json('payload');
            $table->unsignedBigInteger('data_version')->nullable();
            $table->string('status', 20)->default('accepted'); // accepted | duplicate | conflict
            $table->string('conflict_reason', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Monotonic reference data version for delta downloads.
        Schema::create('data_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_versions');
        Schema::dropIfExists('sync_items');
    }
};
