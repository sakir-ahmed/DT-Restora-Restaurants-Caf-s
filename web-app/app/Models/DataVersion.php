<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataVersion extends Model
{
    protected $fillable = ['version'];

    protected $casts = [
        'version' => 'integer',
    ];

    /**
     * The current monotonic reference data version. Bumped whenever
     * menu, prices, tables or settings change so devices can pull a
     * delta instead of a full snapshot.
     */
    public static function current(): int
    {
        $row = self::query()->first();

        if ($row === null) {
            return self::query()->create(['version' => 1])->version;
        }

        return $row->version;
    }

    public static function bump(): int
    {
        $row = self::query()->first() ?? self::query()->create(['version' => 1]);

        $row->increment('version');

        return $row->fresh()->version;
    }
}
