<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings.manage');

        return Inertia::render('admin/settings/Index', [
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
        ], [], [
            'discount_limit_percent' => __('settings.discount_limit'),
            'wastage_spike_multiplier' => __('settings.wastage_spike'),
            'pos_pin_minutes' => __('settings.pos_pin'),
            'pos_lock_enabled' => __('settings.pos_lock_enabled'),
            'delivery_otp_enabled' => __('settings.delivery_otp'),
            'tax_registration_no' => __('settings.tax_registration_no'),
        ]);

        Setting::current()->update($validated);

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn(Setting::current())
            ->log('settings.updated');

        return back()->with('success', __('settings.saved'));
    }
}
