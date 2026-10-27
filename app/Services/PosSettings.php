<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\PaymentMethod;
use Closure;
use Illuminate\Validation\Rule;

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

    /**
     * Sound effects an admin can switch on or off, with the default for each.
     * The sounds themselves are generated in the browser (resources/js/lib/sounds.js).
     */
    public const SOUND_EVENTS = [
        'notification' => true,
        'success' => false,
        'error' => true,
        'item_added' => false,
        'order_created' => true,
        'order_paid' => true,
        'order_held' => true,
        'order_cancelled' => true,
        'online_order' => true,
        'kds_new' => true,
        'kds_preparing' => true,
        'kds_ready' => true,
        'kds_served' => true,
    ];

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
     *     sounds: array{enabled: bool, volume: int, events: array<string, bool>},
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
            'sounds' => self::defaultSounds(),
        ];
    }

    /**
     * @return array{enabled: bool, volume: int, events: array<string, bool>}
     */
    public static function defaultSounds(): array
    {
        return ['enabled' => true, 'volume' => 70, 'events' => self::SOUND_EVENTS];
    }

    /**
     * Just the sound settings of a branch. Cheap enough to send with every page.
     *
     * @return array{enabled: bool, volume: int, events: array<string, bool>}
     */
    public static function soundsFor(?Branch $branch): array
    {
        $stored = is_array($branch?->pos_settings['sounds'] ?? null) ? $branch->pos_settings['sounds'] : [];
        $sounds = self::defaultSounds();

        $sounds['enabled'] = (bool) ($stored['enabled'] ?? $sounds['enabled']);
        $sounds['volume'] = max(0, min(100, (int) ($stored['volume'] ?? $sounds['volume'])));

        foreach (array_keys(self::SOUND_EVENTS) as $event) {
            $sounds['events'][$event] = (bool) ($stored['events'][$event] ?? self::SOUND_EVENTS[$event]);
        }

        return $sounds;
    }

    /**
     * @return array{
     *     actions: array<string, array<string, bool>>,
     *     invoice_layout: string,
     *     invoice_pattern: string,
     *     invoice_seq_digits: int,
     *     payment_methods: array<int, array{method: string, enabled: bool, name: string, name_bn: ?string, type: string}>,
     *     cash_suggestions: array<int, int>,
     *     sounds: array{enabled: bool, volume: int, events: array<string, bool>},
     * }
     */
    public static function for(Branch $branch): array
    {
        $stored = $branch->pos_settings ?? [];
        $methods = self::normalizeMethods($stored['payment_methods'] ?? null);
        $suggestions = $stored['cash_suggestions'] ?? null;

        unset($stored['payment_methods'], $stored['cash_suggestions'], $stored['sounds']);

        $settings = array_replace_recursive(self::defaults(), $stored);
        $settings['payment_methods'] = $methods;
        $settings['sounds'] = self::soundsFor($branch);
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

    /**
     * Validation rules for a branch's `pos` input, shared by the branch form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'pos' => ['sometimes', 'array'],
            'pos.actions' => ['required_with:pos', 'array'],
            'pos.actions.*.enabled' => ['required', 'boolean'],
            'pos.actions.*.exact_amount' => ['required', 'boolean'],
            'pos.actions.*.print_invoice' => ['required', 'boolean'],
            'pos.actions.*.print_kitchen' => ['required', 'boolean'],
            'pos.invoice_layout' => ['required_with:pos', Rule::in(self::LAYOUTS)],
            'pos.invoice_pattern' => ['required_with:pos', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! self::patternIsValid($value)) {
                    $fail(__('settings.invoice_pattern_invalid'));
                }
            }],
            'pos.invoice_seq_digits' => ['required_with:pos', 'integer', 'min:1', 'max:8'],
            'pos.payment_methods' => ['required_with:pos', 'array', 'min:1', function (string $attribute, mixed $value, Closure $fail): void {
                $enabled = collect(is_array($value) ? $value : [])->filter(fn ($entry): bool => is_array($entry) && ! empty($entry['enabled']));

                if ($enabled->isEmpty()) {
                    $fail(__('settings.payment_methods_none'));
                }
            }],
            'pos.payment_methods.*.method' => ['required', Rule::exists('payment_methods', 'code'), 'distinct'],
            'pos.payment_methods.*.enabled' => ['required', 'boolean'],
            'pos.sounds.enabled' => ['required_with:pos', 'boolean'],
            'pos.sounds.volume' => ['required_with:pos', 'integer', 'min:0', 'max:100'],
            'pos.sounds.events' => ['required_with:pos', 'array'],
            'pos.sounds.events.*' => ['boolean'],
            'pos.cash_suggestions' => ['nullable', 'array', 'max:8'],
            'pos.cash_suggestions.*' => ['integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * Turn validated `pos` input into the JSON stored on the branch.
     *
     * @param  array<string, mixed>  $pos
     * @return array<string, mixed>
     */
    public static function fromInput(array $pos): array
    {
        $actions = [];

        foreach (self::ACTIONS as $action) {
            $row = $pos['actions'][$action] ?? null;

            abort_if($row === null, 422);

            $actions[$action] = array_map(fn ($flag): bool => (bool) $flag, $row);
        }

        return [
            'actions' => $actions,
            'invoice_layout' => $pos['invoice_layout'],
            'invoice_pattern' => $pos['invoice_pattern'],
            'invoice_seq_digits' => (int) $pos['invoice_seq_digits'],
            'payment_methods' => self::normalizeMethods($pos['payment_methods']),
            'sounds' => [
                'enabled' => (bool) $pos['sounds']['enabled'],
                'volume' => (int) $pos['sounds']['volume'],
                'events' => array_map(
                    fn (string $event): bool => (bool) ($pos['sounds']['events'][$event] ?? self::SOUND_EVENTS[$event]),
                    array_combine(array_keys(self::SOUND_EVENTS), array_keys(self::SOUND_EVENTS)),
                ),
            ],
            'cash_suggestions' => array_values(array_unique(array_map('intval', $pos['cash_suggestions'] ?? []))),
        ];
    }
}
