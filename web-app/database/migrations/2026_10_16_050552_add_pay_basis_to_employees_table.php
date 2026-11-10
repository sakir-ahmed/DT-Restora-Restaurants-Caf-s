<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('pay_basis', 16)->default('monthly');
            $table->decimal('pay_rate', 12, 2)->default(0);
            $table->string('device_user_id', 64)->nullable();
            $table->unique(['branch_id', 'device_user_id']);
        });

        DB::table('employees')->update(['pay_rate' => DB::raw('monthly_salary')]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'device_user_id']);
            $table->dropColumn(['pay_basis', 'pay_rate', 'device_user_id']);
        });
    }
};
