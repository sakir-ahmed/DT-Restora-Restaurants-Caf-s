<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps visitor mobile numbers and journeys alive across the nightly demo rebuild,
 * which drops every table. Rows are written to a file before the rebuild and read back after.
 */
class DemoJourneyArchive
{
    private const TABLES = ['demo_sessions', 'demo_events'];

    private const CHUNK = 1000;

    /**
     * @return string|null path of the snapshot file, or null when there was nothing to keep
     */
    public function snapshot(): ?string
    {
        if (! Schema::hasTable('demo_sessions')) {
            return null;
        }

        $path = storage_path('app/private/demo-journeys.ndjson');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            return null;
        }

        $written = 0;

        foreach (self::TABLES as $table) {
            DB::table($table)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($handle, $table, &$written): void {
                foreach ($rows as $row) {
                    fwrite($handle, json_encode(['t' => $table, 'r' => (array) $row], JSON_UNESCAPED_UNICODE)."\n");
                    $written++;
                }
            });
        }

        fclose($handle);

        return $written > 0 ? $path : null;
    }

    public function restore(?string $path): int
    {
        if ($path === null || ! is_file($path) || ! Schema::hasTable('demo_sessions')) {
            return 0;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $restored = 0;

        foreach (self::TABLES as $table) {
            rewind($handle);
            $batch = [];

            while (($line = fgets($handle)) !== false) {
                /** @var array{t: string, r: array<string, mixed>}|null $entry */
                $entry = json_decode($line, true);

                if (! is_array($entry) || $entry['t'] !== $table) {
                    continue;
                }

                $batch[] = $entry['r'];

                if (count($batch) >= self::CHUNK) {
                    DB::table($table)->insert($batch);
                    $restored += count($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                DB::table($table)->insert($batch);
                $restored += count($batch);
            }
        }

        fclose($handle);
        unlink($path);

        return $restored;
    }
}
