<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PayBasis;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('employees.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $branchId = $employee instanceof Employee
            ? $employee->branch_id
            : ($this->user('staff')->branch_id ?? (int) session('branch_id', 0));

        return [
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'device_user_id' => [
                'nullable', 'string', 'max:64',
                Rule::unique('employees', 'device_user_id')->where('branch_id', $branchId)->ignore($employee instanceof Employee ? $employee->id : null),
            ],
            'pay_basis' => ['nullable', Rule::in(array_column(PayBasis::cases(), 'value'))],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['required_without:pay_rate', 'nullable', 'numeric', 'min:0'],
            'joined_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }
}
