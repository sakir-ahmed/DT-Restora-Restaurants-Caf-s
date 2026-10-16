<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('orders.create');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'order_type' => ['required', 'in:dine_in,takeaway,delivery'],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
            'client_id' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
            'terminal' => ['nullable', 'string', 'max:10'],
            'discount.type' => ['nullable', 'required_with:discount', 'in:percent,fixed'],
            'discount.value' => ['nullable', 'required_with:discount', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.add_ons' => ['nullable', 'array'],
            'items.*.add_ons.*.id' => ['required', 'integer', 'exists:menu_item_addons,id'],
            'items.*.add_ons.*.qty' => ['nullable', 'integer', 'min:1'],
            'items.*.discount.type' => ['nullable', 'in:percent,fixed'],
            'items.*.discount.value' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.extra_charge' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
