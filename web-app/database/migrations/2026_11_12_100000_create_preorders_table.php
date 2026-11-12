<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preorders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 24)->unique();
            // Opens the guest copy without signing in; random, never the order number.
            $table->string('token', 32)->unique();
            $table->string('source', 12)->default('staff'); // staff | public
            $table->string('customer_name', 80)->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->unsignedSmallInteger('party_size')->nullable();
            $table->timestamp('expected_at')->nullable();
            // A table is optional: a pre-order is not a booking and holds nothing.
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->json('items');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 12)->default('pending')->index(); // pending | completed | cancelled
            $table->timestamp('sms_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        // Deployments only run migrations, so create the permissions here and give them
        // to the roles that already handle reservations.
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $view = Permission::findOrCreate('preorders.view');
            $manage = Permission::findOrCreate('preorders.manage');

            foreach (['owner', 'branch-manager', 'cashier', 'waiter', 'super-admin'] as $name) {
                Role::query()->where('name', $name)->get()->each(fn (Role $role) => $role->givePermissionTo($view, $manage));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preorders');

        Permission::query()->whereIn('name', ['preorders.view', 'preorders.manage'])->delete();
    }
};
