<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Length arithmetic for SMS. A message in the GSM 7-bit alphabet holds 160
 * characters in one part, or 153 per part when it is split; any other
 * character (Bangla, for one) makes it Unicode, 70 per part or 67 when split.
 * The JS copy in resources/js/lib/smsText.js uses the same rules.
 */
final class SmsText
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Each of these costs two septets. */
    private const GSM_EXTENDED = "^{}\\[~]|€\f";

    /** Longest message the system will accept from a template, in SMS parts. */
    public const MAX_PARTS = 4;

    /**
     * @return array{chars: int, words: int, encoding: string, segments: int, per_segment: int, units: int}
     */
    public static function analyze(string $text): array
    {
        $chars = mb_strlen($text);
        $words = preg_match_all('/\S+/u', $text);
        $gsmUnits = self::gsmUnits($text);

        if ($gsmUnits !== null) {
            $single = 160;
            $multi = 153;
            $units = $gsmUnits;
            $encoding = 'gsm7';
        } else {
            $single = 70;
            $multi = 67;
            // Unicode counts UTF-16 code units, so a character outside the basic plane is two.
            $units = intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
            $encoding = 'unicode';
        }

        $segments = $units === 0 ? 0 : ($units <= $single ? 1 : (int) ceil($units / $multi));

        return [
            'chars' => $chars,
            'words' => $words === false ? 0 : $words,
            'encoding' => $encoding,
            'segments' => $segments,
            'per_segment' => $segments > 1 ? $multi : $single,
            'units' => $units,
        ];
    }

    private static function gsmUnits(string $text): ?int
    {
        $units = 0;

        foreach (mb_str_split($text) as $character) {
            if (str_contains(self::GSM_BASIC, $character)) {
                $units++;
            } elseif (str_contains(self::GSM_EXTENDED, $character)) {
                $units += 2;
            } else {
                return null;
            }
        }

        return $units;
    }
}
