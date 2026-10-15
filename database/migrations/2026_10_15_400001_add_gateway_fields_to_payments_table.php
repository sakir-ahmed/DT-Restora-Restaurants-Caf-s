<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway', 20)->nullable()->after('method'); // bkash | sslcommerz | nagad
            $table->string('gateway_payment_id')->nullable()->after('gateway');
            $table->string('gateway_trx_id')->nullable()->after('gateway_payment_id');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('gateway_trx_id');
            $table->timestamp('verified_at')->nullable()->after('idempotency_key');
            $table->index(['gateway', 'gateway_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['gateway', 'gateway_payment_id', 'gateway_trx_id', 'idempotency_key', 'verified_at']);
        });
    }
};
