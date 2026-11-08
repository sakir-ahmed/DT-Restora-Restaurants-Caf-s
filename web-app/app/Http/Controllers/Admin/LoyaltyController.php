<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltySetting;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function index(Request $request, LoyaltyService $loyalty): Response
    {
        $this->authorize('loyalty.manage');

        $lookup = null;
        $mobile = $request->string('mobile')->toString();

        if ($mobile !== '') {
            $customer = Customer::query()->where('mobile', normalize_bd_mobile($mobile))->first();

            $lookup = $customer ? [
                'customer' => $customer->only(['id', 'name', 'mobile']),
                'balance' => $loyalty->balance($customer),
                'entries' => LoyaltyEntry::query()
                    ->where('customer_id', $customer->id)
                    ->with('order:id,invoice_no')
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn (LoyaltyEntry $entry) => [
                        'id' => $entry->id,
                        'type' => $entry->type,
                        'points' => $entry->points,
                        'balance_after' => $entry->balance_after,
                        'note' => $entry->note,
                        'invoice_no' => $entry->order?->invoice_no,
                        'created_at' => $entry->created_at->toIso8601String(),
                    ]),
            ] : null;
        }

        return Inertia::render('admin/loyalty/Index', [
            'settings' => LoyaltySetting::current()->only(['earn_rate', 'point_value', 'expiry_days', 'is_enabled']),
            'lookup' => $lookup,
            'totals' => [
                'customers_with_points' => Customer::query()->whereHas('loyaltyEntries')->count(),
                'points_earned' => (int) LoyaltyEntry::query()->where('points', '>', 0)->where('type', 'earned')->sum('points'),
                'points_redeemed' => abs((int) LoyaltyEntry::query()->where('type', 'redeemed')->sum('points')),
                'points_expired' => abs((int) LoyaltyEntry::query()->where('type', 'expired')->sum('points')),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('loyalty.manage');

        $validated = $request->validate([
            'earn_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'point_value' => ['required', 'numeric', 'min:0'],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_enabled' => ['required', 'boolean'],
        ], [], [
            'earn_rate' => __('loyalty.earn_rate'),
            'point_value' => __('loyalty.point_value'),
            'expiry_days' => __('loyalty.expiry_days'),
            'is_enabled' => __('branches.is_active'),
        ]);

        LoyaltySetting::current()->update($validated);

        return back()->with('success', __('loyalty.settings_saved'));
    }

    public function adjust(Request $request, LoyaltyService $loyalty): RedirectResponse
    {
        $this->authorize('loyalty.manage');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $loyalty->adjust(
            Customer::query()->findOrFail((int) $validated['customer_id']),
            (int) $validated['points'],
            $validated['reason'],
            $request->user('staff'),
        );

        return back()->with('success', __('loyalty.adjusted'));
    }
}
