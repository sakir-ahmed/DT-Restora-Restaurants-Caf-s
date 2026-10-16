<?php

declare(strict_types=1);

if (! function_exists('normalize_bd_mobile')) {
    /**
     * Normalize a Bangladeshi mobile number to the 8801XXXXXXXXX format.
     * Accepts 01XXXXXXXXX, +8801XXXXXXXXX and 8801XXXXXXXXX.
     */
    function normalize_bd_mobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '88'.$digits;
        }

        return $digits;
    }
}

if (! function_exists('is_bd_mobile')) {
    function is_bd_mobile(string $mobile): bool
    {
        return preg_match('/^8801[3-9]\d{8}$/', normalize_bd_mobile($mobile)) === 1;
    }
}

if (! function_exists('money')) {
    /**
     * Format a decimal string amount as a non-float money string with 2 decimals.
     */
    function money(string|int|float|null $amount, int $decimals = 2): string
    {
        $value = number_format((float) ($amount ?? 0), $decimals, '.', '');

        return $value;
    }
}

if (! function_exists('flat_translations')) {
    /**
     * Flatten all lang/{locale} group files into a single key => value map
     * (nav.orders => "Orders") for sharing with Inertia. Pass $groups to keep
     * the payload small on pages that only need a few groups.
     *
     * @param  list<string>|null  $groups
     * @return array<string, string>
     */
    function flat_translations(string $locale, ?array $groups = null): array
    {
        $path = lang_path($locale);

        if (! is_dir($path)) {
            return [];
        }

        $flat = [];

        foreach (glob($path.'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');

            if ($groups !== null && ! in_array($group, $groups, true)) {
                continue;
            }

            $lines = require $file;

            foreach (array_dot($lines) as $key => $value) {
                if (is_string($value)) {
                    $flat[$group.'.'.$key] = $value;
                }
            }
        }

        return $flat;
    }
}

if (! function_exists('array_dot')) {
    /**
     * Flatten a multi-dimensional array into dot notation.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    function array_dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && $value !== []) {
                $results = array_merge($results, array_dot($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }
}
