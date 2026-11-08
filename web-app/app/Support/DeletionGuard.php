<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Builds the "delete these first" message shown when a record still
 * has related data, so every delete action explains itself the same way.
 */
class DeletionGuard
{
    /**
     * @param  array<string, Relation<*, *, *>>  $related  label => relation to count
     * @return array<string, int> label => count, only for relations that have rows
     */
    public static function blockers(array $related): array
    {
        $found = [];

        foreach ($related as $label => $relation) {
            $count = $relation->count();

            if ($count > 0) {
                $found[$label] = $count;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, int>  $blockers
     */
    public static function message(string $name, array $blockers): string
    {
        $list = collect($blockers)
            ->map(fn (int $count, string $label): string => "{$count} {$label}")
            ->implode(', ');

        return __('common.delete_blocked', ['name' => $name, 'related' => $list]);
    }
}
