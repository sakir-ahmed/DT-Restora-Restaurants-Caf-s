<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('attendance.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:64', 'required_without:ip_address'],
            'ip_address' => ['nullable', 'ip', 'required_without:serial'],
            'is_active' => ['boolean'],
        ];
    }
}
