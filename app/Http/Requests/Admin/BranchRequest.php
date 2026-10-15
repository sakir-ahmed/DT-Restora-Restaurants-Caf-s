<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('branches.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $branchId = $this->route('branch')->id ?? 'NULL';

        return [
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:10', 'unique:branches,code,'.$branchId],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'vat_percent' => ['required', 'numeric', 'between:0,100'],
            'vat_inclusive' => ['boolean'],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100'],
            'allow_negative_stock' => ['boolean'],
            'online_ordering_enabled' => ['boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
