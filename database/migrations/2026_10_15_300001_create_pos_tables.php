<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10); // T01
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // Table 12
            $table->string('area', 50)->nullable();
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('status', 20)->default('free'); // free | occupied | reserved
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('terminal_code', 10);
            $table->date('for_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'terminal_code', 'for_date']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->string('order_type', 20); // dine_in | takeaway | delivery
            $table->string('channel', 20)->default('pos'); // pos | website | aggregator
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type', 10)->nullable(); // percent | fixed
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('rounding', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('food_cost', 12, 4)->default(0); // snapshot
            $table->string('client_id', 40)->nullable()->unique(); // idempotency
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_via_payment_id')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->string('name'); // snapshot
            $table->string('name_bn')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->json('add_ons')->nullable(); // [{id, name, price, qty}]
            $table->decimal('add_ons_total', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->decimal('line_cost', 12, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20); // cash | bkash | nagad | card
            $table->decimal('amount', 12, 2);
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->nullable();
            $table->string('reference')->nullable(); // TrxID
            $table->string('status', 20)->default('completed');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terminal_code', 10)->default('T01');
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'user_id', 'closed_at']);
        });

        Schema::create('void_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code', 50);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('void_requests');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('terminals');
    }
};
