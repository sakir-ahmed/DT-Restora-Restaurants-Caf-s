<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Setting;
use App\Services\Sms\Drivers\BdBulkSmsDriver;

/**
 * The SMS providers an owner can choose in Settings. Each one declares the
 * fields it needs (a token, a username and password, a sender id ...), so the
 * Settings page draws the right form and a new provider is one entry here plus
 * one driver class.
 */
final class SmsProviders
{
    public const DEFAULT = 'bdbulksms';

    /**
     * @return array<string, array{name: string, driver: class-string<SmsDriver>, docs: string, fields: array<int, array{key: string, secret: bool, required: bool}>}>
     */
    public static function all(): array
    {
        return [
            'bdbulksms' => [
                'name' => 'BD Bulk SMS',
                'driver' => BdBulkSmsDriver::class,
                'docs' => 'https://bdbulksms.net',
                'fields' => [
                    ['key' => 'token', 'secret' => true, 'required' => true],
                ],
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function current(): string
    {
        $key = Setting::current()->sms_provider;

        return is_string($key) && self::exists($key) ? $key : self::DEFAULT;
    }

    /**
     * Saved credentials of one provider. BD Bulk SMS falls back to the old
     * BDBULKSMS_TOKEN environment value until a token is saved in Settings.
     *
     * @return array<string, string>
     */
    public static function credentials(string $provider): array
    {
        $saved = Setting::current()->sms_config;
        $values = is_array($saved) && is_array($saved[$provider] ?? null) ? $saved[$provider] : [];

        if ($provider === 'bdbulksms' && empty($values['token']) && config('services.bdbulksms.token')) {
            $values['token'] = (string) config('services.bdbulksms.token');
        }

        return array_map(fn ($value): string => (string) $value, $values);
    }

    public static function isConfigured(string $provider): bool
    {
        $values = self::credentials($provider);

        foreach (self::all()[$provider]['fields'] ?? [] as $field) {
            if ($field['required'] && ($values[$field['key']] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    public static function driver(string $provider): SmsDriver
    {
        $class = self::all()[$provider]['driver'];

        return new $class;
    }
}
