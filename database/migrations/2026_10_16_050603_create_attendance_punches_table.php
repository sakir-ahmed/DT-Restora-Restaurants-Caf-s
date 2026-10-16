<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_punches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            $table->string('device_user_id', 64);
            $table->dateTime('punched_at');
            $table->string('direction', 8)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['attendance_device_id', 'device_user_id', 'punched_at'],
                'attendance_punches_idempotent_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
