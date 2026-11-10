<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['purchases', 'wastages', 'expenses'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('delete_reason', 40)->nullable();
                $table->string('delete_note', 500)->nullable();
            });
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->boolean('show_deleted_items')->default(true);
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('deleted_by');
                $table->dropColumn(['deleted_at', 'delete_reason', 'delete_note']);
            });
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('show_deleted_items');
        });
    }
};
