<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteExpenseAction
{
    use ReversesSourceJournal;

    /**
     * Delete an expense entered by mistake: its journal is reversed so the books balance again, and
     * the expense is kept marked as deleted.
     */
    public function execute(Expense $expense, ?User $user = null, ?string $reason = null, ?string $note = null): Expense
    {
        return DB::transaction(function () use ($expense, $user, $reason, $note): Expense {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            $this->reverseJournalsFor($expense, $user, __('expenses.delete_journal_note', ['id' => $expense->id]));

            $this->markDeleted($expense, $user, $reason, $note);

            activity()->performedOn($expense)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('expense.deleted');

            return $expense;
        });
    }
}
