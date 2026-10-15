<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Services\LoyaltyService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POS loyalty redemption: the cashier looks a customer up by mobile
 * (with OTP verification or manager override permission).
 */
class LoyaltyRedemptionController extends Controller
{
    public function store(Request $request, LoyaltyService $loyalty): RedirectResponse
    {
        $this->authorize('loyalty.manage');

        $validated = $request->validate([
            'mobile' => ['required', 'string'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'points' => ['required', 'integer', 'min:1'],
            'otp' => ['nullable', 'string', 'digits:6'],
        ]);

        $customer = Customer::query()
            ->where('mobile', normalize_bd_mobile($validated['mobile']))
            ->firstOrFail();

        $order = Order::query()->findOrFail((int) $validated['order_id']);

        abort_unless($request->user('staff')->canAccessBranch($order->branch_id), 404);

        // OTP verification, or the manager-override permission.
        if (! empty($validated['otp'])) {
            if (! app(OtpService::class)->verify($customer->mobile, (string) $validated['otp'])) {
                return back()->with('error', __('api.otp_invalid'));
            }
        } elseif (! $request->user('staff')->can('discounts.override')) {
            return back()->with('error', __('loyalty.otp_or_override'));
        }

        $entry = $loyalty->redeem($customer, (int) $validated['points'], $order, $request->user('staff'));

        $order->update(['points_redeemed' => (int) $validated['points']]);

        return back()->with('success', __('loyalty.redeemed_at_pos', [
            'points' => $entry->points,
            'value' => number_format(abs((int) $entry->points) * (float) LoyaltySetting::current()->point_value, 2),
        ]));
    }
}
