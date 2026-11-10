<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DemoEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDemoEventsRequest extends FormRequest
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
            'events' => ['required', 'array', 'min:1', 'max:30'],
            'events.*.type' => ['required', 'string', Rule::enum(DemoEventType::class)],
            'events.*.name' => ['nullable', 'string', 'max:120'],
            'events.*.path' => ['nullable', 'string', 'max:200'],
            'events.*.meta' => ['nullable', 'array', 'max:8'],
            'events.*.age_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
