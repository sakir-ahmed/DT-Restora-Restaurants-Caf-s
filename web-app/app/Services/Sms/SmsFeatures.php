<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Setting;

/**
 * Every kind of SMS the software can send, so each can be switched on or off
 * in Settings. The name and description of each feature are in lang/*\/sms.php
 * under "feature.<key>".
 */
final class SmsFeatures
{
    /**
     * group: who receives it. default: on or off until the owner decides.
     * locked: cannot be switched off (sign-in codes would stop working).
     *
     * @var array<string, array{group: string, default: bool, locked: bool}>
     */
    public const ALL = [
        'otp' => ['group' => 'system', 'default' => true, 'locked' => true],
        'order_confirmed' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'invoice' => ['group' => 'customer', 'default' => false, 'locked' => false],
        'delivery_status' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'reservation_confirmed' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'preorder_confirmed' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'menu_link' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'due_reminder' => ['group' => 'customer', 'default' => false, 'locked' => false],
        'birthday' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'win_back' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'promotion' => ['group' => 'customer', 'default' => true, 'locked' => false],
        'owner_daily_summary' => ['group' => 'owner', 'default' => true, 'locked' => false],
    ];

    /** Used for the Settings test message; always allowed, never listed as a feature. */
    public const TEST = 'test';

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function enabled(string $feature): bool
    {
        $definition = self::ALL[$feature] ?? null;

        // Anything not in the list (the test message, ad hoc sends) is always allowed.
        if ($definition === null || $definition['locked']) {
            return true;
        }

        $saved = Setting::current()->sms_features;

        return is_array($saved) && array_key_exists($feature, $saved) ? (bool) $saved[$feature] : $definition['default'];
    }

    /**
     * @return array<string, bool>
     */
    public static function states(): array
    {
        return collect(self::ALL)->map(fn (array $definition, string $key): bool => self::enabled($key))->all();
    }
}
