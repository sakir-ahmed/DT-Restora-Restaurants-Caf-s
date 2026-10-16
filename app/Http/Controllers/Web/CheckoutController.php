<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\RestaurantTable;
use App\Services\CouponService;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request): Response
    {
        $branchId = (int) session('web_branch_id', 0);

        $branch = Branch::query()
            ->where('is_active', true)
            ->when($branchId > 0, fn ($query) => $query->whereKey($branchId), fn ($query) => $query->orderBy('id'))
            ->with(['zones' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        $customer = $request->user('customer');

        return Inertia::render('web/Checkout', [
            'branch' => $branch->only(['id', 'name', 'code', 'vat_percent', 'vat_inclusive', 'service_charge_percent']),
            'zones' => $branch->zones->map(fn (DeliveryZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'fee' => (string) $zone->fee,
                'min_order' => (string) $zone->min_order,
                'eta_minutes' => $zone->eta_minutes,
            ])->values(),
            'customer' => $customer ? $customer->only(['id', 'name', 'mobile']) : null,
            'addresses' => $customer ? $customer->addresses()->with('zone')->get() : [],
            'table' => session('web_table') ? RestaurantTable::query()->find(session('web_table'))?->only(['id', 'name']) : null,
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    /**
     * Validate a coupon code against the cart for the checkout page.
     */
    public function validateCoupon(Request $request, CouponService $coupons)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $branchId = (int) session('web_branch_id', 0);

        try {
            $result = $coupons->validate($validated['code'], (float) $validated['subtotal'], [
                'channel' => 'website',
                'branch_id' => $branchId > 0 ? $branchId : null,
                'customer_id' => $request->user('customer')?->id,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => collect($exception->errors())->flatten()->first()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $result['coupon']->code,
                'type' => $result['coupon']->type,
                'value' => (string) $result['coupon']->value,
                'discount' => number_format($result['discount'], 2, '.', ''),
            ],
        ]);
    }

    public function place(Request $request, CreateOrderAction $action, TurnstileService $turnstile): RedirectResponse
    {
        $validated = $request->validate([
            'order_type' => ['required', 'in:delivery,pickup,dine_in'],
            'delivery_zone_id' => ['required_if:order_type,delivery', 'nullable', 'integer', 'exists:delivery_zones,id'],
            'delivery_address' => ['required_if:order_type,delivery', 'nullable', 'string', 'max:500'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'payment_method' => ['required', 'in:cod,cash,bkash,sslcommerz'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'guest_otp' => ['nullable', 'string', 'digits:6'],
            'turnstile_token' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.add_ons' => ['nullable', 'array'],
            'items.*.add_ons.*.id' => ['required', 'integer', 'exists:menu_item_addons,id'],
            'items.*.add_ons.*.qty' => ['nullable', 'integer', 'min:1'],
        ], [
            'delivery_zone_id.required_if' => __('web.err_zone'),
            'delivery_address.required_if' => __('web.err_address'),
            'items.required' => __('web.cart_empty'),
            'items.min' => __('web.cart_empty'),
            'guest_otp.digits' => __('web.err_otp_length'),
            'scheduled_for.after' => __('web.err_scheduled'),
        ]);

        // Guest checkout rate limit: 5 orders per hour per IP.
        $key = 'guest-checkout:'.sha1((string) $request->ip());

        if (! SmsService::isDemo() && RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'order' => __('web.too_many_orders'),
            ]);
        }

        $customer = $request->user('customer');

        if ($customer === null) {
            if (! SmsService::isDemo()) {
                RateLimiter::hit($key, 3600);
            }

            // Guests must pass Turnstile (when configured) and verify by OTP.
            if (! $turnstile->verify((string) ($validated['turnstile_token'] ?? ''))) {
                throw ValidationException::withMessages([
                    'turnstile' => __('web.turnstile_failed'),
                ]);
            }

            $mobile = normalize_bd_mobile((string) ($validated['customer_mobile'] ?? ''));

            if ($mobile === '' || ! isset($validated['guest_otp'])) {
                throw ValidationException::withMessages([
                    'guest_otp' => __('web.otp_required'),
                ]);
            }

            if (! app(OtpService::class)->verify($mobile, (string) $validated['guest_otp'])) {
                throw ValidationException::withMessages([
                    'guest_otp' => __('api.otp_invalid'),
                ]);
            }

            $customer = Customer::query()->firstOrCreate(
                ['mobile' => $mobile],
                [
                    'name' => $validated['customer_name'] ?? __('web.customer_default_name'),
                    'mobile_verified_at' => now(),
                ],
            );
        }

        $branch = Branch::query()->findOrFail((int) session('web_branch_id', Branch::query()->orderBy('id')->value('id')));

        $type = OrderType::from($validated['order_type']);

        $zone = null;
        $deliveryFee = null;

        if ($type === OrderType::Delivery) {
            $zone = DeliveryZone::query()
                ->where('is_active', true)
                ->where('branch_id', $branch->id)
                ->findOrFail((int) $validated['delivery_zone_id']);

            // Minimum order per zone is enforced here.
            $subtotal = collect($validated['items'])->sum(function (array $line): float {
                return 0; // recalculated server-side after pricing below
            });

            $deliveryFee = (float) $zone->fee;
        }

        $order = $action->execute(
            branch: $branch,
            type: $type,
            lines: $validated['items'],
            customer: $customer,
            channel: OrderChannel::Website,
            terminalCode: 'WEB',
            deliveryFee: $deliveryFee,
            deliveryZoneId: $zone?->id,
            deliveryAddress: $validated['delivery_address'] ?? null,
            scheduledFor: isset($validated['scheduled_for']) ? new \DateTimeImmutable((string) $validated['scheduled_for']) : null,
            tableId: $type === OrderType::DineIn ? ($validated['table_id'] ?? session('web_table')) : null,
            couponCode: $validated['coupon_code'] ?? null,
        );

        // Minimum order check against the real server-side subtotal.
        if ($zone !== null && (float) $order->subtotal < (float) $zone->min_order) {
            $order->forceFill(['status' => 'void'])->save();

            throw ValidationException::withMessages([
                'delivery_zone_id' => __('web.min_order_not_met', ['min' => $zone->min_order]),
            ]);
        }

        // Online order reaches the kitchen immediately.
        broadcast(new OrderCreated($branch->id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_type' => $order->order_type->value,
            'items' => $order->items->map(fn ($item) => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
        ]));

        SendSmsJob::deliver($customer->mobile, __('web.sms_order_confirmed', ['invoice' => $order->invoice_no, 'total' => $order->total]));

        if ($validated['payment_method'] !== 'cod' && in_array($validated['payment_method'], ['bkash', 'sslcommerz'], true)) {
            session(['pending_gateway_order' => $order->id]);

            return redirect()->route('web.payment.start', ['order' => $order->id, 'gateway' => $validated['payment_method']]);
        }

        return redirect()->route('web.track', ['token' => $order->tracking_token])
            ->with('success', __('web.order_placed', ['invoice' => $order->invoice_no]))
            ->with('placed_order', [
                'invoice_no' => $order->invoice_no,
                'total' => (string) $order->total,
                'order_type' => $type->value,
                'payment_method' => $validated['payment_method'],
                'eta_minutes' => $zone?->eta_minutes,
                'branch' => $branch->name,
            ]);
    }
}
