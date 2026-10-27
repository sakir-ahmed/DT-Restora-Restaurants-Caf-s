<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\PosSettings;
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

        $user = $request->user('staff');
        $branches = $user->accessibleBranches();
        $branch = $branches->firstWhere('id', (int) $request->query('branch'))
            ?? $branches->firstWhere('id', (int) session('branch_id', 0))
            ?? $branches->sortBy('id')->first();

        return Inertia::render('admin/settings/Index', [
            'branches' => $branches->map(fn (Branch $entry): array => ['id' => $entry->id, 'name' => $entry->name, 'code' => $entry->code])->values(),
            'branchSettings' => $branch === null ? null : [
                'id' => $branch->id,
                'vat_percent' => $branch->vat_percent,
                'vat_inclusive' => $branch->vat_inclusive,
                'service_charge_percent' => $branch->service_charge_percent,
                'receipt_footer' => $branch->receipt_footer,
                'pos' => PosSettings::for($branch),
            ],
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
            'layouts' => PosSettings::LAYOUTS,
            'defaultPattern' => PosSettings::DEFAULT_PATTERN,
            'settings' => Setting::current()->only([
                'discount_limit_percent',
                'wastage_spike_multiplier',
                'pos_pin_minutes',
                'pos_lock_enabled',
                'delivery_otp_enabled',
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
        ], [], [
            'discount_limit_percent' => __('settings.discount_limit'),
            'wastage_spike_multiplier' => __('settings.wastage_spike'),
            'pos_pin_minutes' => __('settings.pos_pin'),
            'pos_lock_enabled' => __('settings.pos_lock_enabled'),
            'delivery_otp_enabled' => __('settings.delivery_otp'),
        ]);

        Setting::current()->update($validated);

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn(Setting::current())
            ->log('settings.updated');

        return back()->with('success', __('settings.saved'));
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('settings.manage');

        abort_unless($request->user('staff')->canAccessBranch($branch->id), 404);

        $validated = $request->validate([
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_inclusive' => ['required', 'boolean'],
            'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'pos' => ['required', 'array'],
            'pos.actions' => ['required', 'array'],
            'pos.actions.*.enabled' => ['required', 'boolean'],
            'pos.actions.*.exact_amount' => ['required', 'boolean'],
            'pos.actions.*.print_invoice' => ['required', 'boolean'],
            'pos.actions.*.print_kitchen' => ['required', 'boolean'],
            'pos.invoice_layout' => ['required', Rule::in(PosSettings::LAYOUTS)],
            'pos.invoice_pattern' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! PosSettings::patternIsValid($value)) {
                    $fail(__('settings.invoice_pattern_invalid'));
                }
            }],
            'pos.invoice_seq_digits' => ['required', 'integer', 'min:1', 'max:8'],
            'pos.payment_methods' => ['required', 'array', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                $enabled = collect(is_array($value) ? $value : [])->filter(fn ($entry): bool => is_array($entry) && ! empty($entry['enabled']));

                if ($enabled->isEmpty()) {
                    $fail(__('settings.payment_methods_none'));
                }
            }],
            'pos.payment_methods.*.method' => ['required', Rule::exists('payment_methods', 'code'), 'distinct'],
            'pos.payment_methods.*.enabled' => ['required', 'boolean'],
            'pos.sounds.enabled' => ['required', 'boolean'],
            'pos.sounds.volume' => ['required', 'integer', 'min:0', 'max:100'],
            'pos.sounds.events' => ['required', 'array'],
            'pos.sounds.events.*' => ['boolean'],
            'pos.cash_suggestions' => ['nullable', 'array', 'max:8'],
            'pos.cash_suggestions.*' => ['integer', 'min:1', 'max:1000000'],
        ], [], [
            'vat_percent' => __('settings.vat_percent'),
            'service_charge_percent' => __('settings.service_charge'),
            'pos.invoice_pattern' => __('settings.invoice_pattern'),
            'pos.invoice_seq_digits' => __('settings.invoice_digits'),
        ]);

        $actions = [];

        foreach (PosSettings::ACTIONS as $action) {
            $row = $validated['pos']['actions'][$action] ?? null;

            abort_if($row === null, 422);

            $actions[$action] = array_map(fn ($flag): bool => (bool) $flag, $row);
        }

        $branch->update([
            'vat_percent' => $validated['vat_percent'],
            'vat_inclusive' => $validated['vat_inclusive'],
            'service_charge_percent' => $validated['service_charge_percent'],
            'receipt_footer' => $validated['receipt_footer'] ?? null,
            'pos_settings' => [
                'actions' => $actions,
                'invoice_layout' => $validated['pos']['invoice_layout'],
                'invoice_pattern' => $validated['pos']['invoice_pattern'],
                'invoice_seq_digits' => (int) $validated['pos']['invoice_seq_digits'],
                'payment_methods' => PosSettings::normalizeMethods($validated['pos']['payment_methods']),
                'sounds' => [
                    'enabled' => (bool) $validated['pos']['sounds']['enabled'],
                    'volume' => (int) $validated['pos']['sounds']['volume'],
                    'events' => array_map(
                        fn (string $event): bool => (bool) ($validated['pos']['sounds']['events'][$event] ?? PosSettings::SOUND_EVENTS[$event]),
                        array_combine(array_keys(PosSettings::SOUND_EVENTS), array_keys(PosSettings::SOUND_EVENTS)),
                    ),
                ],
                'cash_suggestions' => array_values(array_unique(array_map('intval', $validated['pos']['cash_suggestions'] ?? []))),
            ],
        ]);

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn($branch)
            ->log('settings.branch_updated');

        return back()->with('success', __('settings.saved'));
    }
}
