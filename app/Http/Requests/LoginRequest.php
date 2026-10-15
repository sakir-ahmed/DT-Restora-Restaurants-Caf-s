<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => __('auth.validation.login_required'),
            'password.required' => __('auth.validation.password_required'),
        ];
    }

    /**
     * Credentials resolved from a username, email or BD mobile number.
     *
     * @return array<string, string>
     */
    public function getCredentials(): array
    {
        $login = trim($this->string('login')->toString());

        $field = match (true) {
            filter_var($login, FILTER_VALIDATE_EMAIL) !== false => 'email',
            preg_match('/^(\+?88)?01[3-9]\d{8}$/', $login) === 1 => 'mobile',
            default => 'username',
        };

        if ($field === 'mobile') {
            $login = normalize_bd_mobile($login);
        }

        return [$field => $login, 'password' => $this->input('password')];
    }

    /**
     * Cache key pieces for the login rate limiter.
     *
     * @return array{string, string}
     */
    public function throttleKey(): array
    {
        return [
            strtolower(trim((string) $this->input('login'))),
            (string) $this->ip(),
        ];
    }
}
