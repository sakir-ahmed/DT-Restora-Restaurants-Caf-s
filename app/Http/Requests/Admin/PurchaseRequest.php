<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('purchases.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'purchased_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'receive' => ['boolean'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.ingredient_id' => __('inventory.ingredient'),
            'items.*.unit_id' => __('inventory.unit'),
            'items.*.qty' => __('inventory.qty'),
            'items.*.unit_cost' => __('inventory.unit_cost'),
        ];
    }
}
