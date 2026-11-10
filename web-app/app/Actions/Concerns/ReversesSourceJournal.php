<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ReversesSourceJournal
{
    /**
     * Reverse every posted journal that was posted for the given record, so deleting the record
     * also takes its figures out of the books. Reversal entries are kept for the audit trail.
     */
    protected function reverseJournalsFor(Model $source, ?User $user, string $note): void
    {
        Journal::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', Journal::STATUS_POSTED)
            ->whereNull('reverses_journal_id')
            ->get()
            ->each(fn (Journal $journal) => app(PostJournalEntryAction::class)->reverse($journal, $user, $note));
    }

    protected function markDeleted(Model $record, ?User $user, ?string $reason, ?string $note): void
    {
        $record->forceFill(['deleted_by' => $user?->id, 'delete_reason' => $reason, 'delete_note' => $note])->save();
        $record->delete();
    }
}
