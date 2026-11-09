<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The reasons offered when a mistaken record is deleted. Labels live in lang reasons.*.
 */
class DeleteReasons
{
    public const CODES = ['entry_mistake', 'duplicate_entry', 'wrong_amount', 'other'];

    /**
     * @return array{reason: string, note: string|null}
     */
    public static function validate(Request $request): array
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'in:'.implode(',', self::CODES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ['reason' => $validated['reason_code'], 'note' => $validated['note'] ?? null];
    }
}
