<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('menu.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'description_bn' => ['nullable', 'string', 'max:1000'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'is_vegetarian' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'variants' => ['array'],
            'variants.*.id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
            'variants.*.name_bn' => ['nullable', 'string', 'max:150'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.sort_order' => ['integer', 'min:0'],
            'addons' => ['array'],
            'addons.*.id' => ['nullable', 'integer', 'exists:menu_item_addons,id'],
            'addons.*.name' => ['required_with:addons', 'string', 'max:150'],
            'addons.*.name_bn' => ['nullable', 'string', 'max:150'],
            'addons.*.price' => ['required_with:addons', 'numeric', 'min:0'],
            'addons.*.is_active' => ['boolean'],
            'addons.*.sort_order' => ['integer', 'min:0'],
        ];
    }
}
