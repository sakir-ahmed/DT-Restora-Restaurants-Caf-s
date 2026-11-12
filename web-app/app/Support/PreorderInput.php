<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * What a pre-order form may send. Everything about the guest is optional: only the chosen items
 * are required.
 */
final class PreorderInput
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(int $branchId, bool $withTable): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('branch_id', $branchId)->where('is_available', true)],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.add_ons' => ['nullable', 'array', 'max:10'],
            'items.*.add_ons.*.id' => ['required', 'integer', 'exists:menu_item_addons,id'],
            'items.*.add_ons.*.qty' => ['nullable', 'integer', 'min:1', 'max:9'],
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:300'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            // An exact moment with its UTC offset, sent by the browser.
            'expected_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
            // Staff note money the guest handed over; the public link cannot claim a payment.
            'advance_amount' => $withTable ? ['nullable', 'numeric', 'min:0', 'max:10000000'] : ['prohibited'],
            'restaurant_table_id' => $withTable
                ? ['nullable', 'integer', Rule::exists('restaurant_tables', 'id')->where('branch_id', $branchId)->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function details(array $validated): array
    {
        $text = fn (string $key): ?string => filled($validated[$key] ?? null) ? trim((string) $validated[$key]) : null;

        return [
            'customer_name' => $text('customer_name'),
            'customer_mobile' => $text('customer_mobile'),
            'address' => $text('address'),
            'advance_amount' => filled($validated['advance_amount'] ?? null) ? round((float) $validated['advance_amount'], 2) : 0.0,
            'party_size' => filled($validated['party_size'] ?? null) ? (int) $validated['party_size'] : null,
            'expected_at' => filled($validated['expected_at'] ?? null) ? Carbon::parse($validated['expected_at']) : null,
            'restaurant_table_id' => filled($validated['restaurant_table_id'] ?? null) ? (int) $validated['restaurant_table_id'] : null,
            'note' => $text('note'),
        ];
    }
}
