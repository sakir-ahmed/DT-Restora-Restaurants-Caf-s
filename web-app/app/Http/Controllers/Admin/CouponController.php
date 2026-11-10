<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('coupons.manage');

        return Inertia::render('admin/coupons/Index', [
            'coupons' => Coupon::query()
                ->withCount('redemptions')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('coupons.manage');

        $validated = $this->validated($request);

        Coupon::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        return back()->with('success', __('coupons.created'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('coupons.manage');

        $validated = $this->validated($request, $coupon->id);

        $coupon->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        return back()->with('success', __('coupons.updated'));
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('coupons.manage');

        $coupon->update(['is_active' => false]);

        return back()->with('success', __('coupons.deactivated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $couponId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code,'.$couponId.',id'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['in:pos,website,aggregator'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['boolean'],
        ]) + ['channels' => $request->input('channels')];
    }
}
