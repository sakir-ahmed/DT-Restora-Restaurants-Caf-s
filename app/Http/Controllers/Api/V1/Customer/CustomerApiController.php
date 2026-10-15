<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Sales\ApproveOrderVoidAction;
use App\Actions\Sales\CreateOrderAction;
use App\Actions\Sales\StartGatewayPaymentAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Branch;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\DeviceToken;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Services\CouponService;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    use InteractsWithApi;

    public function me(Request $request): JsonResponse
    {
        $customer = $this->customerUser($request);

        return $this->success([
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'locale' => $customer->locale,
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $customer = $this->customerUser($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'locale' => ['sometimes', 'in:en,bn'],
            'birthday' => ['sometimes', 'nullable', 'date'],
        ]);

        $customer->update($validated);

        return $this->me($request);
    }

    public function addresses(Request $request): JsonResponse
    {
        return $this->success($this->customerUser($request)->addresses()->get());
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $customer = $this->customerUser($request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:500'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $address = $customer->addresses()->create($validated);

        return $this->success($address, null, 201);
    }

    public function updateAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customerUser($request);
        abort_unless($address->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:40'],
            'address' => ['sometimes', 'string', 'max:500'],
        ]);

        $address->update($validated);

        return $this->success($address->fresh());
    }

    public function destroyAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customerUser($request);
        abort_unless($address->customer_id === $customer->id, 404);
        $address->delete();

        return $this->success(null);
    }

    public function branches(): JsonResponse
    {
        return $this->success(Branch::query()->where('is_active', true)->orderBy('name')->get([
            'id', 'code', 'name', 'is_active',
        ]));
    }

    public function menu(string $branch): JsonResponse
    {
        $branchModel = ctype_digit($branch)
            ? Branch::query()->findOrFail($branch)
            : Branch::query()->where('code', $branch)->firstOrFail();

        $categories = MenuCategory::query()
            ->where('branch_id', $branchModel->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns'])])
            ->get();

        return $this->success($categories);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_zone_id' => ['required', 'integer', 'exists:delivery_zones,id'],
        ]);

        $zone = DeliveryZone::query()->where('is_active', true)->findOrFail($validated['delivery_zone_id']);

        return $this->success([
            'zone' => $zone->name,
            'fee' => (string) $zone->fee,
            'minimum_order' => (string) $zone->min_order,
            'eta_minutes' => $zone->eta_minutes,
        ]);
    }

    public function storeOrder(Request $request, CreateOrderAction $action): JsonResponse
    {
        $customer = $this->customerUser($request);

        return $this->rememberIdempotent($request, 'customer-order', function () use ($request, $customer, $action): JsonResponse {
            $validated = $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
                'order_type' => ['required', 'in:dine_in,takeaway,delivery'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.menu_item_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'delivery_zone_id' => ['nullable', 'integer'],
                'delivery_address' => ['nullable', 'string', 'max:500'],
                'coupon_code' => ['nullable', 'string'],
            ]);

            $zone = null;

            if (($validated['order_type'] ?? '') === 'delivery' && ! empty($validated['delivery_zone_id'])) {
                $zone = DeliveryZone::query()->where('is_active', true)->findOrFail($validated['delivery_zone_id']);
            }

            $order = $action->execute(
                branch: Branch::query()->findOrFail($validated['branch_id']),
                type: OrderType::from($validated['order_type']),
                lines: $validated['items'],
                customer: $customer,
                channel: OrderChannel::Website,
                terminalCode: 'APP',
                deliveryFee: $zone !== null ? (float) $zone->fee : null,
                deliveryZoneId: $zone?->id,
                deliveryAddress: $validated['delivery_address'] ?? null,
                couponCode: $validated['coupon_code'] ?? null,
            );

            return $this->success($this->orderPayload($order), null, 201);
        });
    }

    public function orders(Request $request): JsonResponse
    {
        $customer = $this->customerUser($request);

        $orders = Order::query()->where('customer_id', $customer->id)->latest()->paginate(25);

        return $this->success([
            'orders' => collect($orders->items())->map(fn (Order $order) => $this->orderPayload($order))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function showOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $this->customerUser($request)->id, 404);

        return $this->success($this->orderPayload($order));
    }

    public function cancel(Request $request, Order $order, ApproveOrderVoidAction $action): JsonResponse
    {
        abort_unless($order->customer_id === $this->customerUser($request)->id, 404);
        $order = $action->cancelPending($order);

        return $this->success($this->orderPayload($order));
    }

    public function pay(Request $request, Order $order, StartGatewayPaymentAction $action): JsonResponse
    {
        abort_unless($order->customer_id === $this->customerUser($request)->id, 404);

        return $this->rememberIdempotent($request, 'customer-pay', function () use ($request, $order, $action): JsonResponse {
            $validated = $request->validate(['gateway' => ['required', 'in:bkash,nagad,sslcommerz']]);
            $started = $action->execute($order, $validated['gateway'], $request->header('Idempotency-Key'));

            return $this->success([
                'payment_id' => $started['payment']->id,
                'redirect_url' => $started['redirect_url'],
            ]);
        });
    }

    public function tracking(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $this->customerUser($request)->id, 404);

        return $this->success($this->trackingPayload($order));
    }

    public function guestTrack(string $token): JsonResponse
    {
        $order = Order::query()->where('tracking_token', $token)->firstOrFail();

        if ($order->status->value === 'void' || ($order->delivery?->status === 'delivered' && $order->updated_at?->lt(now()->subDay()))) {
            abort(404);
        }

        return $this->success($this->trackingPayload($order));
    }

    public function loyalty(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $customer = $this->customerUser($request);

        return $this->success([
            'balance' => $loyalty->balance($customer),
        ]);
    }

    public function validateCoupon(Request $request, CouponService $coupons): JsonResponse
    {
        $this->customerUser($request);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->success($coupons->validate($validated['code'], (float) $validated['subtotal']));
    }

    public function storeDevice(Request $request): JsonResponse
    {
        $customer = $this->customerUser($request);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:android,ios,web'],
        ]);

        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'platform' => $validated['platform'],
                'owner_type' => $customer->getMorphClass(),
                'owner_id' => $customer->id,
            ],
        );

        return $this->success(['id' => $device->id], null, 201);
    }

    public function destroyDevice(Request $request, string $token): JsonResponse
    {
        $customer = $this->customerUser($request);

        DeviceToken::query()
            ->where('token', $token)
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->id)
            ->delete();

        return $this->success(null);
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingPayload(Order $order): array
    {
        $order->loadMissing('delivery.rider.user');

        return [
            'invoice_no' => $order->invoice_no,
            'status' => $order->status->value,
            'rider' => $order->delivery?->rider === null ? null : [
                'id' => $order->delivery->rider->id,
                'name' => $order->delivery->rider->user?->name,
            ],
        ];
    }
}
