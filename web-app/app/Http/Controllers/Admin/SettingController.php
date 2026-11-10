<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\PosSettings;
use App\Services\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'branchId' => $branch?->id,
            'branchCode' => $branch?->code ?? '',
            'branchPos' => $branch !== null ? [
                ...PosSettings::for($branch),
                'allow_negative_stock' => (bool) $branch->allow_negative_stock,
                'vat_percent' => (string) $branch->vat_percent,
                'vat_inclusive' => (bool) $branch->vat_inclusive,
                'service_charge_percent' => (string) $branch->service_charge_percent,
                'online_ordering_enabled' => (bool) $branch->online_ordering_enabled,
            ] : null,
            'layouts' => PosSettings::LAYOUTS,
            'papers' => PosSettings::PAPERS,
            'invoicePrint' => $branch !== null ? [
                'layout' => PosSettings::for($branch)['invoice_layout'],
                'paper' => PosSettings::for($branch)['invoice_paper'],
            ] : null,
            'defaultPattern' => PosSettings::DEFAULT_PATTERN,
            'deliveryEnabled' => (bool) ($branch?->online_delivery_enabled ?? true),
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
                'delete_confirm_delay_enabled',
                'delete_confirm_delay_seconds',
                'show_deleted_items',
                'delivery_otp_enabled',
                'tax_registration_no',
                'social_facebook',
                'social_instagram',
                'social_youtube',
                'social_tiktok',
                'social_whatsapp',
                'established_year',
                'website_enabled',
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
            'delete_confirm_delay_enabled' => ['sometimes', 'boolean'],
            'delete_confirm_delay_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'show_deleted_items' => ['sometimes', 'boolean'],
            'delivery_otp_enabled' => ['required', 'boolean'],
            'tax_registration_no' => ['nullable', 'string', 'max:40'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_youtube' => ['nullable', 'url:http,https', 'max:255'],
            'social_tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'social_whatsapp' => ['nullable', 'url:http,https', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'website_enabled' => ['sometimes', 'boolean'],
        ], [], [
            'discount_limit_percent' => __('settings.discount_limit'),
            'wastage_spike_multiplier' => __('settings.wastage_spike'),
            'pos_pin_minutes' => __('settings.pos_pin'),
            'pos_lock_enabled' => __('settings.pos_lock_enabled'),
            'delete_confirm_delay_enabled' => __('settings.delete_confirm_delay_enabled'),
            'delete_confirm_delay_seconds' => __('settings.delete_confirm_delay_seconds'),
            'show_deleted_items' => __('settings.show_deleted_items'),
            'delivery_otp_enabled' => __('settings.delivery_otp'),
            'tax_registration_no' => __('settings.tax_registration_no'),
            'social_facebook' => __('settings.social_facebook'),
            'social_instagram' => __('settings.social_instagram'),
            'social_youtube' => __('settings.social_youtube'),
            'social_tiktok' => __('settings.social_tiktok'),
            'social_whatsapp' => __('settings.social_whatsapp'),
            'established_year' => __('settings.established_year'),
            'website_enabled' => __('settings.website_enabled'),
        ]);

        Setting::current()->update($validated);
        PublicCache::bump();

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn(Setting::current())
            ->log('settings.updated');

        return back()->with('success', __('settings.saved'));
    }

    /**
     * Invoice style and paper size of the selected branch.
     */
    public function updateInvoicePrint(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404);

        $validated = $request->validate([
            'layout' => ['required', Rule::in(PosSettings::LAYOUTS)],
            'paper' => ['required', Rule::in(PosSettings::PAPERS)],
        ]);

        $branch->update(['pos_settings' => [
            ...(is_array($branch->pos_settings) ? $branch->pos_settings : []),
            'invoice_layout' => $validated['layout'],
            'invoice_paper' => $validated['paper'],
        ]]);

        activity()->causedBy($request->user('staff'))->performedOn($branch)->log('branch.invoice_print_updated');

        return back()->with('success', __('settings.saved'));
    }

    /**
     * POS and billing options of the selected branch. The same options are in
     * the branch edit form; both write to the same branch record.
     */
    public function updateBranchPos(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404);

        $validated = $request->validate([
            ...PosSettings::rules(),
            'allow_negative_stock' => ['boolean'],
            'vat_percent' => ['required', 'numeric', 'between:0,100'],
            'vat_inclusive' => ['boolean'],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100'],
            'online_ordering_enabled' => ['boolean'],
        ]);

        $branch->update([
            'allow_negative_stock' => (bool) ($validated['allow_negative_stock'] ?? false),
            'vat_percent' => $validated['vat_percent'],
            'vat_inclusive' => (bool) ($validated['vat_inclusive'] ?? false),
            'service_charge_percent' => $validated['service_charge_percent'],
            'online_ordering_enabled' => (bool) ($validated['online_ordering_enabled'] ?? false),
            ...(isset($validated['pos']) ? ['pos_settings' => PosSettings::fromInput($validated['pos'])] : []),
        ]);

        activity()->causedBy($request->user('staff'))->performedOn($branch)->log('branch.pos_settings_updated');

        return back()->with('success', __('settings.saved'));
    }

    private function currentBranch(Request $request): ?Branch
    {
        $user = $request->user('staff');

        return Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0)) ?? Branch::query()->orderBy('id')->first();
    }
}
