<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('tables.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'is_range' => $this->route('table') instanceof RestaurantTable ? false : $this->boolean('is_range'),
        ];

        if ($this->has('name')) {
            $merge['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('prefix')) {
            $merge['prefix'] = trim((string) $this->input('prefix'));
        }

        if ($this->input('area') === '') {
            $merge['area'] = null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->boolean('is_range')) {
            return [
                'is_range' => ['boolean'],
                'prefix' => ['required', 'string', 'max:20', 'not_regex:/[\\/]/'],
                'from' => ['required', 'integer', 'min:1', 'max:500'],
                'to' => ['required', 'integer', 'gte:from', 'max:500'],
                'area' => ['nullable', 'string', 'max:50'],
                'seats' => ['required', 'integer', 'min:1', 'max:30'],
                'is_active' => ['boolean'],
            ];
        }

        return [
            'is_range' => ['boolean'],
            'name' => [
                'required',
                'string',
                'max:50',
                'not_regex:/[\\/]/',
                Rule::unique('restaurant_tables', 'name')->where('branch_id', $this->branchId())->ignore($this->tableId()),
            ],
            'area' => ['nullable', 'string', 'max:50'],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
            'status' => ['sometimes', Rule::in(['free', 'reserved'])],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_range') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $from = (int) $this->input('from');
            $to = (int) $this->input('to');

            if (($to - $from + 1) > 50) {
                $validator->errors()->add('to', __('tables.range_too_large'));
            }

            $longest = $this->string('prefix')->toString().(string) $to;

            if (mb_strlen($longest) > 50) {
                $validator->errors()->add('prefix', __('tables.name_too_long'));
            }
        });
    }

    private function branchId(): int
    {
        $table = $this->route('table');

        if ($table instanceof RestaurantTable) {
            return (int) $table->branch_id;
        }

        $branchId = $this->user('staff')->branch_id ?? (int) $this->session()->get('branch_id', 0);

        if ($branchId > 0 && Branch::query()->whereKey($branchId)->exists()) {
            return $branchId;
        }

        return (int) Branch::query()->orderBy('id')->value('id');
    }

    private function tableId(): ?int
    {
        $table = $this->route('table');

        return $table instanceof RestaurantTable ? $table->id : null;
    }
}
