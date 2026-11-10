<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // A sale completed with money still to collect. Cleared once the balance is settled.
            $table->boolean('is_due')->default(false)->after('status');
            $table->index(['branch_id', 'is_due']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            // Money collected later against a due sale.
            $table->boolean('is_due_collection')->default(false)->after('is_advance');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('is_due_collection');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'is_due']);
            $table->dropColumn('is_due');
        });
    }
};
