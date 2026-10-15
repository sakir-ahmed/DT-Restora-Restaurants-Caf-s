<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('employees.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'joined_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }
}
