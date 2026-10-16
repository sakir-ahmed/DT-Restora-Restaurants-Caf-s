<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

    /**
     * Keep an invoice number the device already printed. The terminal
     * counter moves forward to that number so the next online sale
     * does not collide with it.
     */
    public function claim(Branch $branch, string $terminalCode, string $invoiceNo): string
    {
        $terminalCode = strtoupper($terminalCode);
        $pattern = '/^'.preg_quote($branch->code, '/').'-'.preg_quote($terminalCode, '/').'-(\d{8})-(\d+)$/';

        if (preg_match($pattern, $invoiceNo, $matches) !== 1) {
            return $this->next($branch, $terminalCode);
        }

        try {
            $date = Carbon::createFromFormat('!Ymd', $matches[1]);
        } catch (\Throwable) {
            return $this->next($branch, $terminalCode);
        }

        if (! $date instanceof \DateTimeInterface) {
            return $this->next($branch, $terminalCode);
        }

        if (Order::query()->where('invoice_no', $invoiceNo)->exists()) {
            throw ValidationException::withMessages([
                'invoice_no' => __('sync.invoice_taken'),
            ]);
        }

        $number = (int) $matches[2];

        $sequence = InvoiceSequence::query()
            ->where('branch_id', $branch->id)
            ->where('terminal_code', $terminalCode)
            ->whereDate('for_date', $date->toDateString())
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = InvoiceSequence::query()->create([
                'branch_id' => $branch->id,
                'terminal_code' => $terminalCode,
                'for_date' => $date->toDateString(),
                'last_number' => 0,
            ]);

            $sequence = InvoiceSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
        }

        if ($number > (int) $sequence->last_number) {
            $sequence->last_number = $number;
            $sequence->save();
        }

        return $invoiceNo;
    }
}
