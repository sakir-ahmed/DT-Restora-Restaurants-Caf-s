<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait InteractsWithApi
{
    protected function staffUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan('staff')) {
            abort(401, __('api.unauthenticated'));
        }

        return $user;
    }

    protected function customerUser(Request $request): Customer
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || ! $customer->tokenCan('customer')) {
            abort(401, __('api.unauthenticated'));
        }

        return $customer;
    }

    protected function permit(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            abort(403, __('api.forbidden'));
        }
    }

    protected function branchOrFail(Request $request, string $key): Branch
    {
        $branch = ctype_digit($key)
            ? Branch::query()->find($key)
            : Branch::query()->where('code', $key)->first();

        if ($branch === null) {
            abort(404);
        }

        $user = $request->user();

        if ($user instanceof User && ! $user->canAccessBranch($branch->id)) {
            abort(404);
        }

        return $branch;
    }

    protected function rememberIdempotent(Request $request, string $scope, callable $callback): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            abort(422, __('api.idempotency_required'));
        }

        $cacheKey = 'idempotency:'.$scope.':'.($request->user()?->getAuthIdentifier() ?? 'guest').':'.$key;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return response()->json($cached['body'], $cached['status']);
        }

        $response = $callback();
        Cache::put($cacheKey, [
            'body' => $response->getData(true),
            'status' => $response->getStatusCode(),
        ], now()->addDay());

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderPayload(Order $order): array
    {
        $order->loadMissing(['items', 'payments', 'table', 'cashier', 'voidRequest']);

        return [
            'id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'status' => $order->status->value,
            'order_type' => $order->order_type->value,
            'total' => (string) $order->total,
            'tracking_token' => $order->tracking_token,
            'created_at' => $order->created_at?->toIso8601String(),
            'subtotal' => (string) $order->subtotal,
            'discount_amount' => (string) $order->discount_amount,
            'service_charge' => (string) $order->service_charge,
            'vat_amount' => (string) $order->vat_amount,
            'delivery_fee' => (string) $order->delivery_fee,
            'rounding' => (string) $order->rounding,
            'table' => $order->table?->name,
            'cashier' => $order->cashier?->name,
            'note' => $order->note,
            'void_status' => $order->voidRequest?->status,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'name_bn' => $item->name_bn,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'add_ons' => $item->add_ons,
                'line_total' => (string) $item->line_total,
            ])->all(),
            'payments' => $order->payments->map(fn ($payment) => [
                'method' => $payment->method,
                'amount' => (string) $payment->amount,
                'tendered' => $payment->tendered === null ? null : (string) $payment->tendered,
                'change_due' => $payment->change_due === null ? null : (string) $payment->change_due,
            ])->all(),
        ];
    }
}
