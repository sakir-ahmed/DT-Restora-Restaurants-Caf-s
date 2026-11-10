<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * Editable wording for each automatic SMS. Every template has a fixed list of
 * variables written as {name} (the owner cannot invent new ones), a default in
 * lang/*\/ that is used until the owner writes their own, and one body per
 * language so a Bangla-speaking customer gets Bangla text.
 */
final class SmsTemplates
{
    public const LOCALES = ['en', 'bn'];

    public const MAX_CHARS = 480;

    /**
     * lang: the default text (uses :name placeholders). vars: allowed variables
     * with a sample value for the preview. required: must appear in the text.
     *
     * @var array<string, array{feature: string, lang: string, vars: array<string, string>, required: array<int, string>}>
     */
    public const ALL = [
        'order_confirmed' => ['feature' => 'order_confirmed', 'lang' => 'web.sms_order_confirmed', 'vars' => ['invoice' => 'BR01-0042', 'total' => '1,250.00'], 'required' => ['invoice']],
        'invoice' => ['feature' => 'invoice', 'lang' => 'sms.invoice', 'vars' => ['branch' => 'Arman\'s Kitchen', 'invoice' => 'BR01-0042', 'total' => '1,250.00'], 'required' => ['invoice', 'total']],
        'delivery_picked_up' => ['feature' => 'delivery_status', 'lang' => 'delivery.sms_status.picked_up', 'vars' => ['invoice' => 'BR01-0042'], 'required' => ['invoice']],
        'delivery_delivered' => ['feature' => 'delivery_status', 'lang' => 'delivery.sms_status.delivered', 'vars' => ['invoice' => 'BR01-0042'], 'required' => ['invoice']],
        'delivery_failed' => ['feature' => 'delivery_status', 'lang' => 'delivery.sms_status.failed', 'vars' => ['invoice' => 'BR01-0042'], 'required' => ['invoice']],
        'reservation_confirmed' => ['feature' => 'reservation_confirmed', 'lang' => 'reservations.sms_confirmed', 'vars' => ['booking' => 'RS-1024', 'branch' => 'Arman\'s Kitchen', 'tables' => 'T4, T5', 'when' => 'Fri 14 Nov, 8:00 PM'], 'required' => ['booking']],
        'due_reminder' => ['feature' => 'due_reminder', 'lang' => 'sms.due_reminder', 'vars' => ['name' => 'Rahim', 'balance' => '500.00', 'invoice' => 'BR01-0042', 'branch' => 'Arman\'s Kitchen'], 'required' => ['balance']],
        'birthday' => ['feature' => 'birthday', 'lang' => 'web.sms_birthday', 'vars' => ['name' => 'Rahim'], 'required' => []],
        'win_back' => ['feature' => 'win_back', 'lang' => 'web.sms_winback', 'vars' => ['name' => 'Rahim'], 'required' => []],
        'owner_daily_summary' => ['feature' => 'owner_daily_summary', 'lang' => 'web.owner_daily_summary', 'vars' => ['date' => '2026-11-09', 'count' => '48', 'total' => '52,300.00'], 'required' => []],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function locale(?string $locale): string
    {
        return in_array($locale, self::LOCALES, true) ? $locale : (in_array(app()->getLocale(), self::LOCALES, true) ? app()->getLocale() : 'en');
    }

    /**
     * The built-in text in {variable} form, ready to show in the editor.
     */
    public static function defaultBody(string $key, string $locale): string
    {
        $definition = self::ALL[$key];
        $text = (string) __($definition['lang'], [], $locale);

        foreach (array_keys($definition['vars']) as $variable) {
            $text = preg_replace('/:'.$variable.'\b/', '{'.$variable.'}', $text) ?? $text;
        }

        return $text;
    }

    /**
     * What the owner saved for a language, or null while the default is used.
     */
    public static function customBody(string $key, string $locale): ?string
    {
        $saved = Setting::current()->sms_templates;
        $body = is_array($saved) ? ($saved[$key][$locale] ?? null) : null;

        return is_string($body) && trim($body) !== '' ? $body : null;
    }

    /**
     * Fill in a template. Uses the owner's text for the customer's language
     * when there is one, otherwise the default text for that language.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public static function render(string $key, array $variables, ?string $locale = null): string
    {
        $locale = self::locale($locale);
        $custom = self::customBody($key, $locale);

        if ($custom === null) {
            return (string) __(self::ALL[$key]['lang'], array_map(fn ($value): string => (string) $value, $variables), $locale);
        }

        return self::fill($custom, $variables);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public static function fill(string $body, array $variables): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn (array $match): string => (string) ($variables[$match[1]] ?? ''), $body) ?? $body;
    }

    /**
     * Reject a body that uses unknown variables, leaves out a required one, or
     * is too long to send as a handful of SMS parts.
     *
     * @throws ValidationException
     */
    public static function validate(string $key, string $locale, string $body): void
    {
        $definition = self::ALL[$key];
        $field = $locale;

        if (mb_strlen($body) > self::MAX_CHARS) {
            throw ValidationException::withMessages([$field => __('sms.template_too_long', ['max' => self::MAX_CHARS])]);
        }

        preg_match_all('/\{(\w*)\}/', $body, $found);
        $unknown = array_values(array_unique(array_diff($found[1], array_keys($definition['vars']))));

        if ($unknown !== []) {
            throw ValidationException::withMessages([$field => __('sms.template_unknown_variable', ['names' => implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $unknown))])]);
        }

        // A single brace that is not a variable is almost always a typo.
        $withoutVariables = preg_replace('/\{\w+\}/', '', $body) ?? '';

        if (str_contains($withoutVariables, '{') || str_contains($withoutVariables, '}')) {
            throw ValidationException::withMessages([$field => __('sms.template_stray_brace')]);
        }

        $missing = array_values(array_diff($definition['required'], $found[1]));

        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('sms.template_missing_variable', ['names' => implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $missing))])]);
        }

        if (SmsText::analyze(self::fill($body, $definition['vars']))['segments'] > SmsText::MAX_PARTS) {
            throw ValidationException::withMessages([$field => __('sms.template_too_many_parts', ['max' => SmsText::MAX_PARTS])]);
        }
    }

    /**
     * Save one template. A blank body, or one identical to the default, clears
     * the custom text so the default is used again.
     *
     * @param  array<string, ?string>  $bodies  keyed by locale
     */
    public static function save(string $key, array $bodies): void
    {
        $setting = Setting::current();
        $all = is_array($setting->sms_templates) ? $setting->sms_templates : [];
        $current = is_array($all[$key] ?? null) ? $all[$key] : [];

        foreach (self::LOCALES as $locale) {
            if (! array_key_exists($locale, $bodies)) {
                continue;
            }

            $body = trim((string) $bodies[$locale]);

            if ($body === '' || $body === self::defaultBody($key, $locale)) {
                unset($current[$locale]);

                continue;
            }

            self::validate($key, $locale, $body);
            $current[$locale] = $body;
        }

        if ($current === []) {
            unset($all[$key]);
        } else {
            $all[$key] = $current;
        }

        $setting->update(['sms_templates' => $all === [] ? null : $all]);
    }
}
