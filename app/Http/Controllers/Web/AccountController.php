<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $customer = $request->user('customer');

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('items:id,order_id,name,quantity,line_total')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'status' => $order->status->value,
                'total' => (string) $order->total,
                'created_at' => $order->created_at->toIso8601String(),
                'tracking_token' => $order->tracking_token,
                'items' => $order->items->map(fn ($item) => [
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'line_total' => (string) $item->line_total,
                ]),
            ]);

        return Inertia::render('web/Account', [
            'customer' => $customer->only(['id', 'name', 'mobile', 'email']),
            'orders' => $orders,
            'addresses' => $customer->addresses()->with('zone:id,name,fee')->get(),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zones,id'],
        ]);

        $customer->addresses()->create($validated);

        return back()->with('success', __('web.address_saved'));
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        // Customers can only touch their own records.
        abort_unless($request->user('customer')?->id === $address->customer_id, 404);

        $address->delete();

        return back()->with('success', __('web.address_deleted'));
    }

    /**
     * Anonymize the account. Orders stay, personal fields do not.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $customer->addresses()->delete();
        $customer->tokens()->delete();

        $customer->forceFill([
            'name' => __('customers.anonymized_name'),
            'email' => null,
            'google_id' => null,
            'mobile' => null,
            'password' => null,
            'birthday' => null,
            'is_active' => false,
        ])->save();

        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('web.home')->with('success', __('customers.deleted'));
    }
}
