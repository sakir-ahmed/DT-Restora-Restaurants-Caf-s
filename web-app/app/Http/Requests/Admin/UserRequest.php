<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('staff.manage');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id ?? 'NULL';

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$userId],
            'username' => ['nullable', 'string', 'max:60', 'unique:users,username,'.$userId],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile,'.$userId],
            'password' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'min:8'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(['super-admin', 'owner', 'branch-manager', 'cashier', 'waiter', 'kitchen-manager', 'accountant', 'rider'])],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedCredentials(): array
    {
        $data = collect($this->validated());

        if ($data->get('mobile') !== null) {
            $data['mobile'] = normalize_bd_mobile((string) $data['mobile']);
        }

        if ($data->get('password') === null) {
            $data->forget('password');
        }

        return $data->except('roles')->all();
    }
}
