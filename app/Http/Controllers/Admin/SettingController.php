<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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
            'layouts' => PosSettings::LAYOUTS,
            'defaultPattern' => PosSettings::DEFAULT_PATTERN,
            'settings' => Setting::current()->only([
                'discount_limit_percent',
                'wastage_spike_multiplier',
                'pos_pin_minutes',
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
            'delivery_otp_enabled' => ['required', 'boolean'],
        ], [], [
            'discount_limit_percent' => __('settings.discount_limit'),
            'wastage_spike_multiplier' => __('settings.wastage_spike'),
            'pos_pin_minutes' => __('settings.pos_pin'),
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
            ],
        ]);

        activity()
            ->causedBy($request->user('staff'))
            ->performedOn($branch)
            ->log('settings.branch_updated');

        return back()->with('success', __('settings.saved'));
    }
}
