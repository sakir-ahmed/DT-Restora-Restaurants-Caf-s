<?php

declare(strict_types=1);

namespace Database\Seeders\Production\ArmansKitchen;

use App\Models\Order;
use App\Models\Purchase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DESTRUCTIVE. Deletes every order with its lines, payments, void requests,
 * deliveries, coupon redemptions and order loyalty entries, plus the journals
 * posted from orders and purchases, then loads the costed menu through
 * ResetSeeder. Users, roles, settings, customers and the rest of
 * the accounts are kept.
 *
 * Use it once to start the books clean, when the existing orders are test data.
 * Invoice numbers keep counting from where they were.
 *
 *   php artisan db:seed --class='Database\Seeders\Production\ArmansKitchen\FreshStartSeeder'
 */
class FreshStartSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('journals')
                ->whereIn('source_type', [(new Order)->getMorphClass(), (new Purchase)->getMorphClass()])
                ->delete();

            foreach (['order_items', 'payments', 'void_requests', 'deliveries', 'coupon_redemptions'] as $table) {
                DB::table($table)->delete();
            }

            DB::table('loyalty_entries')->whereNotNull('order_id')->delete();
            DB::table('reservations')->whereNotNull('deposit_order_id')->update(['deposit_order_id' => null]);
            DB::table('orders')->delete();
        });

        $this->call(ResetSeeder::class);
    }
}
