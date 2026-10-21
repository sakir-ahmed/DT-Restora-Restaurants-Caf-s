<?php

declare(strict_types=1);

namespace App\Services\Aggregators;

use App\Models\AggregatorChannel;

/**
 * The partner list lives in config/aggregators.php. This makes sure each
 * partner has a row (switched off until an admin enables it) and tells the
 * admin screen which fields a partner takes.
 */
class PartnerCatalog
{
    private const FIELD_RULES = [
        'environment' => ['nullable', 'in:sandbox,production'],
        'chain_id' => ['nullable', 'string', 'max:64'],
        'client_id' => ['nullable', 'string', 'max:128'],
        'client_secret' => ['nullable', 'string', 'max:255'],
        'webhook_secret' => ['nullable', 'string', 'max:100'],
        'vendor_map' => ['nullable', 'array'],
    ];

    public static function ensureRows(): void
    {
        foreach ((array) config('aggregators.partners') as $key => $partner) {
            AggregatorChannel::query()->firstOrCreate(
                ['key' => $key],
                ['name' => $partner['name'], 'commission_percent' => $partner['commission'], 'is_active' => false],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public static function fields(string $key): array
    {
        return (array) config("aggregators.partners.{$key}.fields", ['webhook_secret']);
    }

    public static function hasApi(string $key): bool
    {
        return (bool) config("aggregators.partners.{$key}.api", false);
    }

    /**
     * Validation rules for the fields this partner takes, plus the
     * always-present commission.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $key): array
    {
        $rules = ['commission_percent' => ['required', 'numeric', 'between:0,100']];

        foreach (self::fields($key) as $field) {
            $rules[$field] = self::FIELD_RULES[$field];
        }

        if (in_array('vendor_map', self::fields($key), true)) {
            $rules['vendor_map.*'] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }
}
