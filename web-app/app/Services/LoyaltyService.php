<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Immutable points ledger. The balance is always the balance_after of
 * the newest entry, and always equals the sum of all entry points.
 */
class LoyaltyService
{
    public function __construct(private readonly LoyaltySetting $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->current()->is_enabled;
    }

    public function balance(Customer $customer): int
    {
        $last = LoyaltyEntry::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->value('balance_after');

        return (int) ($last ?? 0);
    }

    /**
     * Points are earned only on paid orders, on the amount after
     * discount, excluding VAT, service charge and delivery fee.
     */
    public function earnForOrder(Order $order): ?LoyaltyEntry
    {
        if (! $this->isEnabled() || $order->customer_id === null || ! $order->isPaid()) {
            return null;
        }

        $settings = $this->settings->current();

        $eligible = (float) $order->subtotal - (float) $order->discount_amount;

        $points = (int) floor($eligible / 100 * $settings->earn_rate);

        if ($points <= 0) {
            return null;
        }

        return $this->append(
            customer: Customer::query()->findOrFail($order->customer_id),
            type: LoyaltyEntry::TYPE_EARNED,
            points: $points,
            orderId: $order->id,
            expiresAt: now()->addDays($settings->expiry_days),
            note: __('loyalty.earned_note', ['invoice' => $order->invoice_no]),
        );
    }

    /**
     * Redeem points against an order. Points cannot pay VAT, service
     * charge or delivery fee; callers pass the eligible base.
     */
    public function redeem(Customer $customer, int $points, ?Order $order = null, ?User $staff = null): LoyaltyEntry
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => __('loyalty.points_positive')]);
        }

        return DB::transaction(function () use ($customer, $points, $order, $staff): LoyaltyEntry {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $balance = $this->balance($customer);

            if ($balance < $points) {
                throw ValidationException::withMessages(['points' => __('loyalty.insufficient_points', ['balance' => $balance])]);
            }

            return $this->append(
                customer: $customer,
                type: LoyaltyEntry::TYPE_REDEEMED,
                points: -$points,
                orderId: $order?->id,
                note: __('loyalty.redeemed_note', ['invoice' => $order->invoice_no ?? '-']),
                user: $staff,
            );
        });
    }

    /**
     * Void or refund reverses both earned and redeemed points.
     */
    public function reverseForOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $entries = LoyaltyEntry::query()
                ->where('order_id', $order->id)
                ->whereIn('type', [LoyaltyEntry::TYPE_EARNED, LoyaltyEntry::TYPE_REDEEMED])
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $customer = Customer::query()->whereKey($entry->customer_id)->lockForUpdate()->firstOrFail();

                $this->append(
                    customer: $customer,
                    type: LoyaltyEntry::TYPE_ADJUSTED,
                    points: -$entry->points,
                    orderId: $order->id,
                    note: __('loyalty.reversal_note', ['invoice' => $order->invoice_no]),
                );
            }
        });
    }

    /**
     * Manual adjustment, permission and reason required.
     */
    public function adjust(Customer $customer, int $points, string $reason, User $staff): LoyaltyEntry
    {
        if ($points === 0) {
            throw ValidationException::withMessages(['points' => __('loyalty.points_nonzero')]);
        }

        $entry = $this->append(
            customer: $customer,
            type: LoyaltyEntry::TYPE_ADJUSTED,
            points: $points,
            note: $reason,
            user: $staff,
        );

        activity()
            ->performedOn($entry)
            ->causedBy($staff)
            ->withProperties(['points' => $points, 'reason' => $reason])
            ->log('loyalty.adjusted');

        return $entry;
    }

    /**
     * Expire unredeemed points past their expiry date. Redemptions
     * consume the oldest earned points first (FIFO). Runs daily.
     *
     * @return int number of expiry entries created
     */
    public function expireDue(): int
    {
        $count = 0;

        LoyaltyEntry::query()
            ->where('type', LoyaltyEntry::TYPE_EARNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('points', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$count): void {
                foreach ($entries as $entry) {
                    $redeemedAfter = abs((int) LoyaltyEntry::query()
                        ->where('customer_id', $entry->customer_id)
                        ->where('id', '>', $entry->id)
                        ->whereIn('type', [LoyaltyEntry::TYPE_REDEEMED, LoyaltyEntry::TYPE_EXPIRED])
                        ->sum('points'));

                    $earnedAfter = (int) LoyaltyEntry::query()
                        ->where('customer_id', $entry->customer_id)
                        ->where('id', '>', $entry->id)
                        ->where('type', LoyaltyEntry::TYPE_EARNED)
                        ->sum('points');

                    $toExpire = max(0, min((int) $entry->points, (int) $entry->points - max(0, $redeemedAfter - $earnedAfter)));

                    if ($toExpire <= 0) {
                        continue;
                    }

                    $this->append(
                        customer: Customer::query()->findOrFail($entry->customer_id),
                        type: LoyaltyEntry::TYPE_EXPIRED,
                        points: -$toExpire,
                        note: __('loyalty.expired_note'),
                    );

                    // Mark the source as processed so it never expires twice.
                    LoyaltyEntry::query()->whereKey($entry->id)->update(['expires_at' => null]);

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Append a locked, immutable ledger entry.
     */
    private function append(
        Customer $customer,
        string $type,
        int $points,
        ?int $orderId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?string $note = null,
        ?User $user = null,
    ): LoyaltyEntry {
        return DB::transaction(function () use ($customer, $type, $points, $orderId, $expiresAt, $note, $user): LoyaltyEntry {
            $latest = LoyaltyEntry::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $balance = (int) ($latest->balance_after ?? 0) + $points;

            return LoyaltyEntry::query()->create([
                'customer_id' => $customer->id,
                'order_id' => $orderId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'expires_at' => $expiresAt,
                'note' => $note,
                'user_id' => $user?->id,
            ]);
        });
    }
}
