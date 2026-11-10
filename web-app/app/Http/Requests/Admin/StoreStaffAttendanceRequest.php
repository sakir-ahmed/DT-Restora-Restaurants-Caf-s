<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\StaffAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffAttendanceRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'status' => ['required', Rule::in([StaffAttendance::STATUS_PRESENT, StaffAttendance::STATUS_ABSENT])],
            'clock_in' => ['exclude_if:status,absent', 'required_if:status,present', 'nullable', 'date'],
            'clock_out' => ['exclude_if:status,absent', 'nullable', 'date', 'after:clock_in'],
        ];
    }
}
