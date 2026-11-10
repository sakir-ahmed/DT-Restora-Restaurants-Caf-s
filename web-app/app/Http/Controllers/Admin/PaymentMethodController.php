<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The shared list of payment methods (cash, wallets, bank POS terminals).
 * Each branch then picks which of them its POS offers and in what order.
 */
class PaymentMethodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,19}$/', 'unique:payment_methods,code'],
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in($this->creatableTypes())],
            'is_active' => ['required', 'boolean'],
        ], [], [
            'code' => __('settings.method_code'),
            'name' => __('settings.method_name'),
        ]);

        $method = PaymentMethod::query()->create($validated + [
            'sort_order' => (int) PaymentMethod::query()->max('sort_order') + 1,
        ]);

        activity()->causedBy($request->user('staff'))->performedOn($method)->log('payment_method.created');

        return back()->with('success', __('settings.method_saved'));
    }

    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        $this->authorize('settings.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'type' => [$method->is_system ? 'prohibited' : 'required', Rule::in($this->creatableTypes())],
            'is_active' => ['required', 'boolean'],
        ], [], [
            'name' => __('settings.method_name'),
        ]);

        // Cash is what the shift close and quick save rely on, so it always stays on.
        if ($method->code === 'cash') {
            $validated['is_active'] = true;
        }

        $method->update($validated);

        activity()->causedBy($request->user('staff'))->performedOn($method)->log('payment_method.updated');

        return back()->with('success', __('settings.method_saved'));
    }

    public function destroy(Request $request, PaymentMethod $method): RedirectResponse
    {
        $this->authorize('settings.manage');

        if ($method->is_system) {
            return back()->with('error', __('settings.method_system'));
        }

        if (Payment::query()->where('method', $method->code)->exists()) {
            return back()->with('error', __('settings.method_in_use'));
        }

        $method->delete();

        activity()->causedBy($request->user('staff'))->log('payment_method.deleted');

        return back()->with('success', __('settings.method_deleted'));
    }

    /**
     * New methods are wallets, card or bank POS terminals, or other: cash stays the one built-in method.
     *
     * @return array<int, string>
     */
    private function creatableTypes(): array
    {
        return [PaymentMethod::TYPE_WALLET, PaymentMethod::TYPE_CARD, PaymentMethod::TYPE_OTHER];
    }
}
