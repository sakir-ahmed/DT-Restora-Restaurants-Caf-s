<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('transfers.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:from_branch_id'],
            'from_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
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
            'items.required' => __('inventory.line_ingredient_required'),
            'items.min' => __('inventory.line_ingredient_required'),
            'to_branch_id.different' => __('inventory.transfer_same_branch'),
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
            'to_branch_id' => __('inventory.to_branch'),
            'from_branch_id' => __('inventory.from_branch'),
        ];
    }
}
