<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DemoJourneyArchive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

#[Signature('demo:reset')]
#[Description('Rebuild the database from migrations and seeders. Runs only when IS_DEMO is true.')]
class ResetDemoCommand extends Command
{
    public function __construct(private readonly DemoJourneyArchive $journeys)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('app.is_demo') !== true) {
            $this->error('Demo reset runs only when IS_DEMO is true.');

            return self::FAILURE;
        }

        // The lock lives on the file cache so migrate:fresh cannot delete it
        // by dropping the database cache table mid-run.
        $store = Cache::store('file');

        if (! $store instanceof LockProvider) {
            $this->error('Demo reset needs a cache store that can hold a lock.');

            return self::FAILURE;
        }

        $lock = $store->lock('demo-reset', 1800);

        if (! $lock->get()) {
            $this->warn('A demo reset is already running.');

            return self::SUCCESS;
        }

        try {
            ini_set('memory_limit', '1G');
            $this->info('Resetting the demo database.');

            $snapshot = $this->journeys->snapshot();

            $status = $this->call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            if ($status === self::SUCCESS) {
                $this->info('Restored '.$this->journeys->restore($snapshot).' demo journey rows.');
            }

            return $status;
        } finally {
            $lock->release();
        }
    }
}
