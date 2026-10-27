<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What a booking does besides holding tables: a pre-ordered menu that goes to the kitchen when the
 * guests sit down, and booking money that is collected up front and taken off their bill.
 */
class ReservationService
{
    public function __construct(private readonly CreateOrderAction $orders) {}

    /**
     * Price the pre-ordered lines from the current menu and keep what the slip needs to show.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, total: float}
     */
    public function priceLines(Branch $branch, array $lines): array
    {
        $priced = [];
        $total = 0.0;

        foreach ($lines as $index => $line) {
            $item = MenuItem::query()
                ->where('branch_id', $branch->id)
                ->available()
                ->with(['variants', 'addOns'])
                ->find((int) $line['menu_item_id']);

            if ($item === null) {
                throw ValidationException::withMessages(["items.{$index}.menu_item_id" => __('orders.item_not_available')]);
            }

            $variant = null;

            if ($item->variants->isNotEmpty()) {
                $variant = $item->variants->firstWhere('id', (int) ($line['variant_id'] ?? 0))
                    ?? $item->variants->firstWhere('is_default', true)
                    ?? $item->variants->first();
            }

            $addOns = [];
            $unit = (float) ($variant?->price ?? $item->base_price);

            foreach ((array) ($line['add_ons'] ?? []) as $addOnLine) {
                $addOn = $item->addOns->where('is_active', true)->firstWhere('id', (int) ($addOnLine['id'] ?? 0));

                if ($addOn === null) {
                    continue;
                }

                $qty = max(1, min(9, (int) ($addOnLine['qty'] ?? 1)));
                $unit += (float) $addOn->price * $qty;
                $addOns[] = ['id' => $addOn->id, 'qty' => $qty, 'name' => $addOn->name, 'name_bn' => $addOn->name_bn, 'price' => (string) $addOn->price];
            }

            $quantity = max(1, min(50, (int) $line['quantity']));
            $lineTotal = round($unit * $quantity, 2);
            $total += $lineTotal;

            $priced[] = [
                'menu_item_id' => $item->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
                'add_ons' => $addOns,
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'variant_name' => $variant?->name,
                'variant_name_bn' => $variant?->name_bn,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ];
        }

        return ['lines' => $priced, 'total' => round($total, 2)];
    }

    /**
     * The guests sat down: send their pre-ordered food to the kitchen on the main table.
     */
    public function sendPreorder(Reservation $reservation, User $user): ?Order
    {
        $lines = (array) $reservation->preorder;

        if ($lines === []) {
            return null;
        }

        $branch = Branch::query()->findOrFail($reservation->branch_id);
        $table = $reservation->tables()->withoutGlobalScopes()->first();
        $shift = Shift::query()->where('branch_id', $branch->id)->where('user_id', $user->id)->whereNull('closed_at')->first();

        $order = $this->orders->execute(
            branch: $branch,
            type: OrderType::DineIn,
            lines: array_map(fn (array $line): array => [
                'menu_item_id' => $line['menu_item_id'],
                'variant_id' => $line['variant_id'] ?? null,
                'quantity' => $line['quantity'],
                'add_ons' => array_map(fn (array $addOn): array => ['id' => $addOn['id'], 'qty' => $addOn['qty']], $line['add_ons'] ?? []),
            ], $lines),
            cashier: $user,
            tableId: $table?->id,
            note: __('reservations.preorder_note', ['booking' => $reservation->booking_no, 'guest' => $reservation->guest_name]),
            channel: OrderChannel::Pos,
            shift: $shift,
            terminalCode: 'RSV',
        );

        broadcast(new OrderCreated($branch->id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_type' => $order->order_type->value,
            'table' => $table?->name,
            'items' => $order->items->map(fn ($item): array => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
        ]));

        return $order;
    }

    /**
     * Take booking money. It is held until the guests order (then it comes off the bill),
     * or until it is given back or kept.
     */
    public function collectDeposit(Reservation $reservation, float $amount, string $methodCode, ?string $reference, User $user): void
    {
        if ($reservation->deposit_status !== Reservation::DEPOSIT_NONE) {
            throw ValidationException::withMessages(['deposit_amount' => __('reservations.deposit_already')]);
        }

        $method = PaymentMethod::query()->active()->where('code', $methodCode)->first();

        if ($method === null) {
            throw ValidationException::withMessages(['deposit_method' => __('payments.invalid_payment')]);
        }

        if ($method->isWallet() && blank($reference)) {
            throw ValidationException::withMessages(['deposit_reference' => __('reservations.deposit_reference_needed')]);
        }

        $reservation->update([
            'deposit_amount' => round($amount, 2),
            'deposit_applied' => 0,
            'deposit_status' => Reservation::DEPOSIT_HELD,
            'deposit_method' => $method->code,
            'deposit_reference' => filled($reference) ? trim((string) $reference) : null,
            'deposit_paid_at' => now(),
        ]);

        activity()->performedOn($reservation)->causedBy($user)->withProperties(['amount' => $amount, 'method' => $method->code])->log('reservations.deposit_collected');
    }

    /**
     * Close what is left of the booking money: give it back, or keep it (a no show, a late cancel).
     */
    public function closeDeposit(Reservation $reservation, string $outcome, User $user): void
    {
        DB::transaction(function () use ($reservation, $outcome, $user): void {
            $reservation = Reservation::query()->withoutBranchScope()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->deposit_status !== Reservation::DEPOSIT_HELD || $reservation->depositBalance() <= 0) {
                throw ValidationException::withMessages(['deposit_amount' => __('reservations.deposit_nothing_held')]);
            }

            $reservation->update([
                'deposit_status' => $outcome === 'refund' ? Reservation::DEPOSIT_REFUNDED : Reservation::DEPOSIT_FORFEITED,
                'deposit_closed_at' => now(),
            ]);

            activity()->performedOn($reservation)->causedBy($user)->withProperties(['amount' => $reservation->depositBalance()])->log('reservations.deposit_'.$outcome);
        });
    }
}
