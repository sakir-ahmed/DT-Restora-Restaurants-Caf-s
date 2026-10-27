<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\PaymentMethod;

/**
 * Branch-wise POS configuration kept as JSON on the branch: which POS
 * buttons are on and what each one prints, the invoice layout and the
 * invoice number pattern. Missing keys fall back to the defaults.
 */
final class PosSettings
{
    public const LAYOUTS = ['classic', 'compact', 'bold'];

    public const ACTIONS = ['send_to_kitchen', 'confirm_payment', 'quick_save'];

    public const FLAGS = ['enabled', 'exact_amount', 'print_invoice', 'print_kitchen'];

    public const TOKENS = ['BRANCH', 'TERMINAL', 'YYYY', 'YY', 'MM', 'DD', 'SEQ'];

    public const DEFAULT_CASH_SUGGESTIONS = [1000, 500, 200, 100];

    public const DEFAULT_PATTERN = '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{SEQ}';

    /**
     * @return array{
     *     actions: array<string, array<string, bool>>,
     *     invoice_layout: string,
     *     invoice_pattern: string,
     *     invoice_seq_digits: int,
     *     payment_methods: array<int, array{method: string, enabled: bool, name: string, name_bn: ?string, type: string}>,
     *     cash_suggestions: array<int, int>,
     * }
     */
    public static function defaults(): array
    {
        return [
            'actions' => [
                'send_to_kitchen' => ['enabled' => true, 'exact_amount' => false, 'print_invoice' => false, 'print_kitchen' => false],
                'confirm_payment' => ['enabled' => true, 'exact_amount' => false, 'print_invoice' => false, 'print_kitchen' => false],
                'quick_save' => ['enabled' => false, 'exact_amount' => true, 'print_invoice' => true, 'print_kitchen' => false],
            ],
            'invoice_layout' => 'classic',
            'invoice_pattern' => self::DEFAULT_PATTERN,
            'invoice_seq_digits' => 4,
            'payment_methods' => self::normalizeMethods(null),
            'cash_suggestions' => self::DEFAULT_CASH_SUGGESTIONS,
        ];
    }

    /**
     * @return array{
     *     actions: array<string, array<string, bool>>,
     *     invoice_layout: string,
     *     invoice_pattern: string,
     *     invoice_seq_digits: int,
     *     payment_methods: array<int, array{method: string, enabled: bool, name: string, name_bn: ?string, type: string}>,
     *     cash_suggestions: array<int, int>,
     * }
     */
    public static function for(Branch $branch): array
    {
        $stored = $branch->pos_settings ?? [];
        $methods = self::normalizeMethods($stored['payment_methods'] ?? null);
        $suggestions = $stored['cash_suggestions'] ?? null;

        unset($stored['payment_methods'], $stored['cash_suggestions']);

        $settings = array_replace_recursive(self::defaults(), $stored);
        $settings['payment_methods'] = $methods;
        $settings['cash_suggestions'] = is_array($suggestions)
            ? array_values(array_map('intval', $suggestions))
            : self::DEFAULT_CASH_SUGGESTIONS;

        // The quick save button always pays the exact total, and the kitchen button never takes cash.
        $settings['actions']['quick_save']['exact_amount'] = true;
        $settings['actions']['send_to_kitchen']['exact_amount'] = false;

        return $settings;
    }

    /**
     * Active payment methods in the order the branch chose, each on or off.
     * Methods the branch never saw (created later) come last and enabled;
     * methods that are deleted or switched off globally are dropped.
     *
     * @return array<int, array{method: string, enabled: bool, name: string, name_bn: ?string, type: string}>
     */
    public static function normalizeMethods(mixed $stored): array
    {
        $known = PaymentMethod::query()->active()->orderBy('sort_order')->orderBy('id')->get()->keyBy('code');
        $result = [];

        foreach (is_array($stored) ? $stored : [] as $entry) {
            $key = is_array($entry) ? ($entry['method'] ?? null) : null;

            if (is_string($key) && $known->has($key) && ! isset($result[$key])) {
                $result[$key] = self::methodEntry($known->get($key), (bool) ($entry['enabled'] ?? true));
            }
        }

        foreach ($known as $key => $method) {
            if (! isset($result[$key])) {
                $result[$key] = self::methodEntry($method, true);
            }
        }

        return array_values($result);
    }

    /**
     * @return array{method: string, enabled: bool, name: string, name_bn: ?string, type: string}
     */
    private static function methodEntry(PaymentMethod $method, bool $enabled): array
    {
        return [
            'method' => $method->code,
            'enabled' => $enabled,
            'name' => $method->name,
            'name_bn' => $method->name_bn,
            'type' => $method->type,
        ];
    }

    /**
     * Keys of the payment methods the branch accepts, in display order.
     *
     * @return array<int, string>
     */
    public static function enabledMethods(Branch $branch): array
    {
        $enabled = array_column(array_filter(self::for($branch)['payment_methods'], fn (array $entry): bool => $entry['enabled']), 'method');

        return array_values($enabled);
    }

    /**
     * An invoice number pattern is valid when it keeps the sequence token once,
     * carries branch, terminal and date, and only uses known tokens and safe separators.
     */
    public static function patternIsValid(string $pattern): bool
    {
        if (preg_match('/^[A-Za-z0-9\-_\/.{}]{1,40}$/', $pattern) !== 1) {
            return false;
        }

        preg_match_all('/\{([^{}]*)\}/', $pattern, $matches);

        if (substr_count($pattern, '{') !== count($matches[1]) || substr_count($pattern, '}') !== count($matches[1])) {
            return false;
        }

        foreach ($matches[1] as $token) {
            if (! in_array($token, self::TOKENS, true)) {
                return false;
            }
        }

        $tokens = $matches[1];

        // Sequences restart per branch, terminal and day, so all of those must show in the number to keep it unique.
        $hasDate = (in_array('YYYY', $tokens, true) || in_array('YY', $tokens, true))
            && in_array('MM', $tokens, true)
            && in_array('DD', $tokens, true);

        return count(array_keys($tokens, 'SEQ', true)) === 1
            && in_array('BRANCH', $tokens, true)
            && in_array('TERMINAL', $tokens, true)
            && $hasDate;
    }
}
