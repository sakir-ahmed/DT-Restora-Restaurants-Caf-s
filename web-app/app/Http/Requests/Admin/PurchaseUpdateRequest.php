<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('purchases.manage');
    }

    /**
     * A draft has touched nothing yet. A received purchase can also be fully corrected, and the correction
     * reverses and re-posts stock, cost and the ledger. Cancelled is read-only.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        /** @var Purchase $purchase */
        $purchase = $this->route('purchase');

        if ($purchase->status === Purchase::STATUS_CANCELLED) {
            return $rules;
        }

        return [
            ...$rules,
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.ingredient_id.required' => __('inventory.line_ingredient_required'),
            'items.*.unit_id.required' => __('inventory.line_unit_required'),
            'items.*.qty.required' => __('inventory.line_qty_required'),
            'items.*.qty.gt' => __('inventory.line_qty_required'),
            'items.*.unit_cost.required' => __('inventory.line_cost_required'),
            'items.required' => __('inventory.line_ingredient_required'),
            'items.min' => __('inventory.line_ingredient_required'),
        ];
    }
}
