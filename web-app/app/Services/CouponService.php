<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coupon validation and redemption. Usage limits are enforced inside
 * a transaction with a row lock, so concurrent requests cannot
 * overspend a coupon.
 */
class CouponService
{
    /**
     * Validate a coupon against a cart without recording anything.
     *
     * @param  array{channel?: ?string, branch_id?: ?int, customer_id?: ?int}  $context
     * @return array{discount: float, coupon: Coupon}
     */
    public function validate(string $code, float $subtotal, array $context = []): array
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages(['coupon' => __('coupons.not_found')]);
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages(['coupon' => __('coupons.not_started')]);
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages(['coupon' => __('coupons.expired')]);
        }

        if ((float) $coupon->min_order > 0 && $subtotal < (float) $coupon->min_order) {
            throw ValidationException::withMessages(['coupon' => __('coupons.min_order', ['min' => $coupon->min_order])]);
        }

        if ($coupon->channels !== null && ($context['channel'] ?? null) !== null && ! in_array($context['channel'], $coupon->channels, true)) {
            throw ValidationException::withMessages(['coupon' => __('coupons.wrong_channel')]);
        }

        if ($coupon->branch_id !== null && ($context['branch_id'] ?? null) !== null && (int) $coupon->branch_id !== (int) $context['branch_id']) {
            throw ValidationException::withMessages(['coupon' => __('coupons.wrong_branch')]);
        }

        if ($coupon->usage_limit_total !== null && $coupon->redemptions()->count() >= $coupon->usage_limit_total) {
            throw ValidationException::withMessages(['coupon' => __('coupons.exhausted')]);
        }

        if ($coupon->usage_limit_per_customer !== null && ($context['customer_id'] ?? null) !== null) {
            $used = $coupon->redemptions()->where('customer_id', $context['customer_id'])->count();

            if ($used >= $coupon->usage_limit_per_customer) {
                throw ValidationException::withMessages(['coupon' => __('coupons.customer_limit_reached')]);
            }
        }

        $discount = $coupon->type === 'percent'
            ? round($subtotal * ((float) $coupon->value) / 100, 2)
            : round((float) $coupon->value, 2);

        return ['discount' => min($discount, $subtotal), 'coupon' => $coupon];
    }

    /**
     * Record the redemption under a lock. The coupon row is locked so
     * two concurrent orders cannot both spend the last usages.
     */
    public function redeem(Coupon $coupon, Order $order): CouponRedemption
    {
        return DB::transaction(function () use ($coupon, $order): CouponRedemption {
            $coupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();

            if ($coupon->usage_limit_total !== null && $coupon->redemptions()->count() >= $coupon->usage_limit_total) {
                throw ValidationException::withMessages(['coupon' => __('coupons.exhausted')]);
            }

            if ($coupon->usage_limit_per_customer !== null && $order->customer_id !== null) {
                $used = $coupon->redemptions()->where('customer_id', $order->customer_id)->count();

                if ($used >= $coupon->usage_limit_per_customer) {
                    throw ValidationException::withMessages(['coupon' => __('coupons.customer_limit_reached')]);
                }
            }

            return CouponRedemption::query()->create([
                'coupon_id' => $coupon->id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
            ]);
        });
    }
}
