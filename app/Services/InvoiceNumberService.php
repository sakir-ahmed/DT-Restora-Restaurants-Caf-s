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

        return $this->format($branch, $terminalCode, $date, (int) $sequence->last_number);
    }

    /**
     * Build an invoice number from the branch pattern, for example
     * {BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{SEQ} gives BR01-T02-20260101-0001.
     */
    public function format(Branch $branch, string $terminalCode, \DateTimeInterface $date, int $number): string
    {
        $settings = PosSettings::for($branch);

        return strtr($settings['invoice_pattern'], [
            '{BRANCH}' => $branch->code,
            '{TERMINAL}' => strtoupper($terminalCode),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{SEQ}' => str_pad((string) $number, max(1, (int) $settings['invoice_seq_digits']), '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Read the sequence (and the date, when the pattern carries a full one)
     * back out of an invoice number that follows the branch pattern.
     *
     * @return array{number: int, date: Carbon}|null
     */
    private function parse(Branch $branch, string $terminalCode, string $invoiceNo): ?array
    {
        $pattern = PosSettings::for($branch)['invoice_pattern'];
        $regex = '';

        foreach (preg_split('/(\{[A-Z]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $regex .= match ($part) {
                '{BRANCH}' => preg_quote($branch->code, '/'),
                '{TERMINAL}' => preg_quote($terminalCode, '/'),
                '{YYYY}' => '(?<year>\d{4})',
                '{YY}' => '(?<short>\d{2})',
                '{MM}' => '(?<month>\d{2})',
                '{DD}' => '(?<day>\d{2})',
                '{SEQ}' => '(?<seq>\d+)',
                default => preg_quote($part, '/'),
            };
        }

        if (preg_match('/^'.$regex.'$/', $invoiceNo, $matches) !== 1) {
            return null;
        }

        $year = $matches['year'] ?? (isset($matches['short']) ? '20'.$matches['short'] : null);
        $date = now()->startOfDay();

        if ($year !== null && isset($matches['month'], $matches['day'])) {
            try {
                $date = Carbon::createFromFormat('!Y-m-d', "{$year}-{$matches['month']}-{$matches['day']}") ?: $date;
            } catch (\Throwable) {
                return null;
            }
        }

        return ['number' => (int) $matches['seq'], 'date' => $date];
    }

    /**
     * Keep an invoice number the device already printed. The terminal
     * counter moves forward to that number so the next online sale
     * does not collide with it.
     */
    public function claim(Branch $branch, string $terminalCode, string $invoiceNo): string
    {
        $terminalCode = strtoupper($terminalCode);
        $parsed = $this->parse($branch, $terminalCode, $invoiceNo);

        if ($parsed === null) {
            return $this->next($branch, $terminalCode);
        }

        $date = $parsed['date'];

        if (Order::query()->where('invoice_no', $invoiceNo)->exists()) {
            throw ValidationException::withMessages([
                'invoice_no' => __('sync.invoice_taken'),
            ]);
        }

        $number = $parsed['number'];

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
