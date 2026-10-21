<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('recipes.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['present', 'array'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.*.ingredient_id.required' => __('inventory.line_ingredient_required'),
            'lines.*.qty.required' => __('inventory.line_qty_required'),
            'lines.*.qty.gt' => __('inventory.line_qty_required'),
            'lines.*.ingredient_id.distinct' => __('inventory.recipe_duplicate_ingredient'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines.*.ingredient_id' => __('inventory.ingredient'),
            'lines.*.qty' => __('inventory.qty'),
        ];
    }
}
