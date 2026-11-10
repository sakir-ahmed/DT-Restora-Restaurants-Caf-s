<?php

declare(strict_types=1);

use Database\Seeders\UnitSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Adds milligram, teaspoon, tablespoon and cup. The seeder is
     * idempotent, so existing units and their ids are untouched.
     */
    public function up(): void
    {
        (new UnitSeeder)->run();
    }

    public function down(): void {}
};
