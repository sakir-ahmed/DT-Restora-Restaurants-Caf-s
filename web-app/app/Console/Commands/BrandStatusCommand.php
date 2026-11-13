<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Brand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('brand:status')]
#[Description('Show which parts of this install use the customer brand and which still use the DT Restora default.')]
class BrandStatusCommand extends Command
{
    public function handle(): int
    {
        if (Brand::isDemo()) {
            $this->info('IS_DEMO is true: this install always uses the DT Restora brand.');

            return self::SUCCESS;
        }

        $this->line('Brand name: '.Brand::name());

        $rows = array_map(fn (array $row): array => [$row['item'], $row['ready'] ? 'customer' : 'DT Restora default', $row['ready'] ? '' : $row['hint']], Brand::status());

        $this->table(['Item', 'Using', 'To change it'], $rows);

        return self::SUCCESS;
    }
}
