<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('staff') !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user('staff')->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'username' => ['nullable', 'string', 'max:60', Rule::unique('users', 'username')->ignore($userId)],
            'mobile' => ['nullable', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($userId)],
            'locale' => ['required', Rule::in(['en', 'bn'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A login needs at least one identifier, so they cannot all be cleared.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! filled($this->input('email')) && ! filled($this->input('username')) && ! filled($this->input('mobile'))) {
                    $validator->errors()->add('email', __('profile.identifier_required'));
                }
            },
        ];
    }
}
