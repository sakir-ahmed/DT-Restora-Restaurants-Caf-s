<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\InvoiceSequence;

/**
 * Sequential invoice numbers per branch and terminal, no gaps:
 * BR01-T02-20260101-0001. Website orders use terminal code WEB.
 * Must be called inside the order's transaction: the row is locked
 * while incrementing.
 */
class InvoiceNumberService
{
    public function next(Branch $branch, string $terminalCode, ?\DateTimeInterface $date = null): string
    {
        $date ??= now();

        $sequence = InvoiceSequence::query()
            ->where('branch_id', $branch->id)
            ->where('terminal_code', $terminalCode)
            ->whereDate('for_date', $date->format('Y-m-d'))
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = InvoiceSequence::query()->create([
                'branch_id' => $branch->id,
                'terminal_code' => $terminalCode,
                'for_date' => $date->format('Y-m-d'),
                'last_number' => 0,
            ]);

            $sequence = InvoiceSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
        }

        $sequence->last_number += 1;
        $sequence->save();

        return sprintf(
            '%s-%s-%s-%04d',
            $branch->code,
            strtoupper($terminalCode),
            $date->format('Ymd'),
            $sequence->last_number,
        );
    }
}
