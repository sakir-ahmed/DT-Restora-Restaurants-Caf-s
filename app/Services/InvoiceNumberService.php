<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Invoice numbers, built from the branch pattern. Two kinds of pattern:
 *
 * - Random (the default): AK01-20261028-143205-K7Q4, the time of day plus a
 *   random code. A terminal can make the number alone, online or offline, so
 *   parallel offline terminals never need to agree on a counter.
 * - Sequence: BR01-T02-20260101-0001, a counter per branch, terminal and day.
 *   The row is locked while incrementing, so call it inside the order's
 *   transaction.
 *
 * Website orders use terminal code WEB. A number is never refused: when a
 * device's number is already taken, claim() issues a fresh one and the sync
 * reports it back to the device.
 */
class InvoiceNumberService
{
    /** No I, L, O or U, so a printed number is never misread. */
    private const RANDOM_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const RANDOM_LENGTH = 4;

    private const RANDOM_ATTEMPTS = 25;

    public function next(Branch $branch, string $terminalCode, ?\DateTimeInterface $date = null): string
    {
        $date ??= now();

        if (! PosSettings::usesSequence(PosSettings::for($branch)['invoice_pattern'])) {
            return $this->random($branch, $terminalCode, $date);
        }

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
    public function format(Branch $branch, string $terminalCode, \DateTimeInterface $date, int $number, ?string $random = null): string
    {
        $settings = PosSettings::for($branch);

        return strtr($settings['invoice_pattern'], [
            '{BRANCH}' => $branch->code,
            '{TERMINAL}' => strtoupper($terminalCode),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{TIME}' => $date->format('His'),
            '{RAND}' => $random ?? $this->randomCode(),
            '{SEQ}' => str_pad((string) $number, max(1, (int) $settings['invoice_seq_digits']), '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * A random invoice number that no order has yet. Retries on the rare repeat.
     */
    private function random(Branch $branch, string $terminalCode, \DateTimeInterface $date): string
    {
        for ($attempt = 0; $attempt < self::RANDOM_ATTEMPTS; $attempt++) {
            $candidate = $this->format($branch, $terminalCode, $date, 0, $this->randomCode());

            if (! Order::query()->where('invoice_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not make a unique invoice number.');
    }

    private function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $code .= self::RANDOM_ALPHABET[random_int(0, strlen(self::RANDOM_ALPHABET) - 1)];
        }

        return $code;
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
                '{TIME}' => '\d{6}',
                '{RAND}' => '['.self::RANDOM_ALPHABET.']{'.self::RANDOM_LENGTH.'}',
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

        return ['number' => (int) ($matches['seq'] ?? 0), 'date' => $date];
    }

    /**
     * Keep the invoice number the device already printed. When the number is
     * not in the branch pattern, or an order already has it, a fresh number is
     * issued instead, so a synced sale is never lost. The caller compares the
     * result with the number it sent and tells the device when they differ.
     * For a sequence pattern the terminal counter moves forward to the
     * device's number, so the next online sale does not collide with it.
     */
    public function claim(Branch $branch, string $terminalCode, string $invoiceNo): string
    {
        $terminalCode = strtoupper($terminalCode);
        $parsed = $this->parse($branch, $terminalCode, $invoiceNo);

        if ($parsed === null || Order::query()->where('invoice_no', $invoiceNo)->exists()) {
            return $this->next($branch, $terminalCode);
        }

        if (! PosSettings::usesSequence(PosSettings::for($branch)['invoice_pattern'])) {
            return $invoiceNo;
        }

        $number = $parsed['number'];

        $sequence = InvoiceSequence::query()
            ->where('branch_id', $branch->id)
            ->where('terminal_code', $terminalCode)
            ->whereDate('for_date', $parsed['date']->toDateString())
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = InvoiceSequence::query()->create([
                'branch_id' => $branch->id,
                'terminal_code' => $terminalCode,
                'for_date' => $parsed['date']->toDateString(),
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
