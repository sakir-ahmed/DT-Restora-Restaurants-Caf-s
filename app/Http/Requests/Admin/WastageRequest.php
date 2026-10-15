<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Wastage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class WastageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('wastage.log');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason_code' => ['required', 'string', Rule::in(Wastage::REASONS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
