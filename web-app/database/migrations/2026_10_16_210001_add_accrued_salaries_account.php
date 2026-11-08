<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['code' => '2300'],
            [
                'name' => 'Accrued salaries',
                'type' => 'liability',
                'subtype' => 'salary',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '2300')->delete();
    }
};
