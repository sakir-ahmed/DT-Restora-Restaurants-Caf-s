<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('staff')?->can('letters.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'recipient' => ['nullable', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'letter_date' => ['required', 'date'],
        ];
    }
}
