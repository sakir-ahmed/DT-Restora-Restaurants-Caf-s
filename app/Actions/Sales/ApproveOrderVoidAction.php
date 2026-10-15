<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\VoidRequest;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveOrderVoidAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Manager approval restores stock exactly once and voids the order.
     * Paid orders keep their payment as a refund record (Phase 10
     * reverses the accounting side).
     */
    public function execute(VoidRequest $request, User $approver): Order
    {
        return DB::transaction(function () use ($request, $approver): Order {
            $request = VoidRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages([
                    'request' => __('orders.void_already_decided'),
                ]);
            }

            $order = Order::query()->whereKey($request->order_id)->lockForUpdate()->firstOrFail();

            $request->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'decided_at' => now(),
            ]);

            $order->update(['status' => OrderStatus::Void->value]);

            $this->restoreStock($order, $approver);

            if ($order->restaurant_table_id !== null) {
                RestaurantTable::query()
                    ->whereKey($order->restaurant_table_id)
                    ->update(['status' => 'free']);
            }

            activity()
                ->performedOn($order)
                ->causedBy($approver)
                ->withProperties(['reason' => $request->reason_code])
                ->log('order.voided');

            return $order;
        });
    }

    private function restoreStock(Order $order, User $approver): void
    {
        $recipes = Recipe::query()
            ->with('items')
            ->whereIn('menu_item_id', $order->items->pluck('menu_item_id'))
            ->get()
            ->keyBy('menu_item_id');

        foreach ($order->items as $orderItem) {
            $recipe = $recipes->get($orderItem->menu_item_id);

            if ($recipe === null) {
                continue;
            }

            foreach ($recipe->items as $line) {
                $this->stock->adjust(
                    branchId: $order->branch_id,
                    ingredientId: $line->ingredient_id,
                    baseQtyDelta: (float) $line->qty * $orderItem->quantity,
                    movementType: 'sale',
                    reference: $order,
                    userId: $approver->id,
                    allowNegative: true,
                );
            }
        }
    }
}
