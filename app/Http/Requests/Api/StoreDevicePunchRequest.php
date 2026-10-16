<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevicePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_user_id' => ['required', 'string', 'max:64'],
            'punched_at' => ['required', 'date'],
            'direction' => ['nullable', 'in:in,out'],
        ];
    }
}
