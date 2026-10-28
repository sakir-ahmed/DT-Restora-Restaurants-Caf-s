<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings.manage');

        $branch = $this->currentBranch($request);

        return Inertia::render('admin/settings/Index', [
            'branchName' => $branch?->name ?? '',
            'deliveryZones' => DeliveryZone::query()
                ->where('branch_id', $branch?->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (DeliveryZone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'name_bn' => $zone->name_bn,
                    'fee' => (string) $zone->fee,
                    'fee_type' => $zone->fee_type,
                    'min_order' => (string) $zone->min_order,
                    'eta_minutes' => $zone->eta_minutes,
                    'is_active' => $zone->is_active,
                    'sort_order' => $zone->sort_order,
                ])->values(),
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PaymentMethod $method): array => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'name_bn' => $method->name_bn,
                'type' => $method->type,
                'is_system' => $method->is_system,
                'is_active' => $method->is_active,
                'sort_order' => $method->sort_order,
                'payments_count' => Payment::query()->where('method', $method->code)->count(),
            ])->values(),
            'settings' => Setting::current()->only([
                'discount_limit_percent',
                'wastage_spike_multiplier',
                'pos_pin_minutes',
                'pos_lock_enabled',
                'delivery_otp_enabled',
                'tax_registration_no',
                'social_facebook',
                'social_instagram',
                'social_youtube',
                'social_tiktok',
                'social_whatsapp',
                'established_year',
                'website_enabled',
                'online_delivery_enabled',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $validated = $request->validate([
            'discount_limit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'wastage_spike_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'pos_pin_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'pos_lock_enabled' => ['sometimes', 'boolean'],
            'delivery_otp_enabled' => ['required', 'boolean'],
            'tax_registration_no' => ['nullable', 'string', 'max:40'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_youtube' => ['nullable', 'url:http,https', 'max:255'],
            'social_tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'social_whatsapp' => ['nullable', 'url:http,https', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'website_enabled' => ['sometimes', 'boolean'],
            'online_delivery_enabled' => ['sometimes', 'boolean'],
        ], [], [
            'discount_limit_percent' => __('settings.discount_limit'),
            'wastage_spike_multiplier' => __('settings.wastage_spike'),
            'pos_pin_minutes' => __('settings.pos_pin'),
            'pos_lock_enabled' => __('settings.pos_lock_enabled'),
            'delivery_otp_enabled' => __('settings.delivery_otp'),
            'tax_registration_no' => __('settings.tax_registration_no'),
            'social_facebook' => __('settings.social_facebook'),
            'social_instagram' => __('settings.social_instagram'),
            'social_youtube' => __('settings.social_youtube'),
            'social_tiktok' => __('settings.social_tiktok'),
            'social_whatsapp' => __('settings.social_whatsapp'),
            'established_year' => __('settings.established_year'),
            'website_enabled' => __('settings.website_enabled'),
            'online_delivery_enabled' => __('settings.online_delivery_enabled'),
        ]);

        Setting::current()->update($validated);
        PublicCache::bump();

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn(Setting::current())
            ->log('settings.updated');

        return back()->with('success', __('settings.saved'));
    }

    private function currentBranch(Request $request): ?Branch
    {
        $user = $request->user('staff');

        return Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0)) ?? Branch::query()->orderBy('id')->first();
    }
}
