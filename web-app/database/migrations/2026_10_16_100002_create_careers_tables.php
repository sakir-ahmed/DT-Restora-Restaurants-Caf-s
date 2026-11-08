<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('title_bn', 150)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_type', 20)->default('full_time');
            $table->string('summary', 300);
            $table->string('summary_bn', 300)->nullable();
            $table->text('description');
            $table->text('description_bn')->nullable();
            $table->text('requirements')->nullable();
            $table->text('requirements_bn')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('closes_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'closes_on']);
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_opening_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('mobile', 20);
            $table->string('email', 150)->nullable();
            $table->text('message')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_openings');
    }
};
