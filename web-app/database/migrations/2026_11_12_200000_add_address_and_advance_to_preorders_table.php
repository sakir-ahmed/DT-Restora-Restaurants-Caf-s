<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preorders', function (Blueprint $table): void {
            $table->string('address', 300)->nullable()->after('customer_mobile');
            // Money the guest handed over in advance, kept on the pre-order and deducted on the slip.
            $table->decimal('advance_amount', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('preorders', function (Blueprint $table): void {
            $table->dropColumn(['address', 'advance_amount']);
        });
    }
};
