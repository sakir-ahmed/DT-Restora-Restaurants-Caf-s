<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('booking_no', 20)->unique();
            $table->string('guest_name', 120);
            $table->string('guest_mobile', 20)->nullable();
            $table->unsignedSmallInteger('party_size')->default(2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 12)->default('booked'); // booked | seated | completed | cancelled | no_show
            $table->string('note', 200)->nullable();

            // Food the guests ordered ahead: lines to send to the kitchen when they sit down.
            $table->json('preorder')->nullable();
            $table->decimal('preorder_total', 12, 2)->default(0);

            // Booking money: collected at booking, taken off the bill when the guests order.
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->decimal('deposit_applied', 12, 2)->default(0);
            $table->string('deposit_status', 10)->default('none'); // none | held | applied | refunded | forfeited
            $table->string('deposit_method', 20)->nullable();
            $table->string('deposit_reference', 100)->nullable();
            $table->timestamp('deposit_paid_at')->nullable();
            $table->timestamp('deposit_closed_at')->nullable();
            $table->foreignId('deposit_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
        });

        // A booking can hold several tables, in the order the staff picked them (the first is the main one).
        Schema::create('reservation_table', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['reservation_id', 'restaurant_table_id']);
            $table->index('restaurant_table_id');
        });

        // A table is either free or occupied now. A booking is a reservation, not a floor status.
        DB::table('restaurant_tables')->where('status', 'reserved')->update(['status' => 'free']);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_table');
        Schema::dropIfExists('reservations');
    }
};
