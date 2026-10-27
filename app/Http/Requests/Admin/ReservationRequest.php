<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('staff')?->can('reservations.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->branchId();
        $creating = ! $this->route('reservation') instanceof Reservation;

        return [
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_mobile' => ['nullable', 'string', 'max:20'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'table_ids' => ['required', 'array', 'min:1', 'max:12'],
            'table_ids.*' => ['integer', 'distinct', Rule::exists('restaurant_tables', 'id')->where('branch_id', $branchId)->where('is_active', true)],
            // Exact moments with their UTC offset, sent by the browser.
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:200'],

            'items' => ['nullable', 'array', 'max:40'],
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('branch_id', $branchId)->where('is_available', true)],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.add_ons' => ['nullable', 'array', 'max:10'],
            'items.*.add_ons.*.id' => ['required', 'integer', 'exists:menu_item_addons,id'],
            'items.*.add_ons.*.qty' => ['nullable', 'integer', 'min:1', 'max:9'],

            // Booking money can be taken while making the booking; later it has its own button.
            'deposit_amount' => $creating ? ['nullable', 'numeric', 'min:0', 'max:10000000'] : ['prohibited'],
            'deposit_method' => $creating ? ['nullable', 'required_with:deposit_amount', 'string', 'max:20'] : ['prohibited'],
            'deposit_reference' => $creating ? ['nullable', 'string', 'max:100'] : ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->moment('starts_at');
            $to = $this->moment('ends_at');

            if ($from->diffInHours($to) > 12) {
                $validator->errors()->add('ends_at', __('reservations.too_long'));

                return;
            }

            $current = $this->route('reservation');

            if (Reservation::clashes(array_map('intval', (array) $this->input('table_ids')), $from, $to, $current instanceof Reservation ? $current->id : null)) {
                $validator->errors()->add('table_ids', __('reservations.clash'));
            }
        });
    }

    public function moment(string $field): Carbon
    {
        return Carbon::parse((string) $this->input($field))->setTimezone((string) config('app.timezone'));
    }

    public function branchId(): int
    {
        $reservation = $this->route('reservation');

        if ($reservation instanceof Reservation) {
            return (int) $reservation->branch_id;
        }

        $branchId = $this->user('staff')->branch_id ?? (int) $this->session()->get('branch_id', 0);

        if ($branchId > 0 && Branch::query()->whereKey($branchId)->exists()) {
            return $branchId;
        }

        return (int) Branch::query()->orderBy('id')->value('id');
    }
}
