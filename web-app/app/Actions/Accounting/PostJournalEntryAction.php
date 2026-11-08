<?php

declare(strict_types=1);

namespace App\Actions\Accounting;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single posting path for all accounting. Controllers and other
 * Actions never write journal lines directly. Entries must balance,
 * postings happen in the caller's transaction, and locked fiscal
 * periods reject new postings.
 *
 * @phpstan-type JournalLineInput array{account_code: string, debit?: float|string, credit?: float|string}
 */
class PostJournalEntryAction
{
    /**
     * @param  array<int, JournalLineInput>  $lines
     */
    public function execute(
        string $entryDate,
        array $lines,
        ?string $reference = null,
        ?string $note = null,
        ?Model $source = null,
        ?User $user = null,
        bool $draft = false,
        bool $allowReversalIntoLocked = false,
    ): Journal {
        return DB::transaction(function () use ($entryDate, $lines, $reference, $note, $source, $user, $draft, $allowReversalIntoLocked): Journal {
            $date = new \DateTimeImmutable($entryDate);

            $this->assertPeriodOpen($date, $allowReversalIntoLocked);

            $prepared = $this->prepareLines($lines);

            $debits = round(array_sum(array_map(fn (array $line) => (float) ($line['debit'] ?? 0), $prepared)), 2);
            $credits = round(array_sum(array_map(fn (array $line) => (float) ($line['credit'] ?? 0), $prepared)), 2);

            if (abs($debits - $credits) > 0.001) {
                throw ValidationException::withMessages([
                    'lines' => __('accounting.not_balanced', ['debits' => $debits, 'credits' => $credits]),
                ]);
            }

            $journal = Journal::query()->create([
                'reference' => $reference,
                'entry_date' => $date->format('Y-m-d'),
                'note' => $note,
                'status' => $draft ? Journal::STATUS_DRAFT : Journal::STATUS_POSTED,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'created_by' => $user?->id,
                'posted_by' => $draft ? null : $user?->id,
                'posted_at' => $draft ? null : now(),
            ]);

            foreach ($prepared as $line) {
                $journal->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => number_format((float) ($line['debit'] ?? 0), 2, '.', ''),
                    'credit' => number_format((float) ($line['credit'] ?? 0), 2, '.', ''),
                ]);
            }

            if (! $draft) {
                activity()->performedOn($journal)->causedBy($user)->log('journal.posted');
            }

            return $journal->load('lines.account');
        });
    }

    /**
     * Posted entries are immutable; corrections post a reversal.
     */
    public function reverse(Journal $journal, ?User $user = null, ?string $note = null): Journal
    {
        return DB::transaction(function () use ($journal, $user, $note): Journal {
            $journal = Journal::query()->whereKey($journal->id)->lockForUpdate()->firstOrFail();

            if (! $journal->isPosted()) {
                throw ValidationException::withMessages([
                    'journal' => __('accounting.only_posted_can_reverse'),
                ]);
            }

            $lines = $journal->lines->map(fn (JournalLine $line) => [
                'account_code' => $line->account->code,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
            ])->all();

            $reversal = $this->execute(
                entryDate: $journal->entry_date->toDateString(),
                lines: $lines,
                reference: $journal->reference,
                note: $note ?? __('accounting.reversal_of', ['id' => $journal->id]),
                user: $user,
                allowReversalIntoLocked: true,
            );

            $reversal->update(['reverses_journal_id' => $journal->id]);
            $journal->update(['status' => Journal::STATUS_REVERSED]);

            activity()->performedOn($reversal)->causedBy($user)->log('journal.reversed');

            return $reversal;
        });
    }

    /**
     * @param  array<int, JournalLineInput>  $lines
     * @return array<int, array{account_id: int, debit?: float, credit?: float}>
     */
    private function prepareLines(array $lines): array
    {
        $codes = array_values(array_unique(array_map(fn (array $line) => (string) $line['account_code'], $lines)));

        $accounts = Account::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        $prepared = [];

        foreach ($lines as $line) {
            $account = $accounts->get((string) $line['account_code']);

            if ($account === null) {
                throw ValidationException::withMessages([
                    'lines' => __('accounting.unknown_account', ['code' => $line['account_code']]),
                ]);
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw ValidationException::withMessages([
                    'lines' => __('accounting.line_invalid'),
                ]);
            }

            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }

            $prepared[] = ['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit];
        }

        return $prepared;
    }

    private function assertPeriodOpen(\DateTimeInterface $date, bool $allowReversalIntoLocked): void
    {
        $year = FiscalYear::query()
            ->whereDate('starts_at', '<=', $date->format('Y-m-d'))
            ->whereDate('ends_at', '>=', $date->format('Y-m-d'))
            ->first();

        if ($year !== null && $year->is_locked && ! $allowReversalIntoLocked) {
            throw ValidationException::withMessages([
                'entry_date' => __('accounting.period_locked'),
            ]);
        }
    }
}
