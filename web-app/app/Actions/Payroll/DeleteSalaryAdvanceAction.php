<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Actions\Concerns\ReversesSourceJournal;
use App\Models\SalaryAdvance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteSalaryAdvanceAction
{
    use ReversesSourceJournal;

    /**
     * Delete a salary advance recorded by mistake: its journal is reversed and it no longer counts
     * against the employee's pay. The advance is kept marked as deleted.
     */
    public function execute(SalaryAdvance $advance, ?User $user = null, ?string $reason = null, ?string $note = null): SalaryAdvance
    {
        return DB::transaction(function () use ($advance, $user, $reason, $note): SalaryAdvance {
            $advance = SalaryAdvance::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();

            $this->reverseJournalsFor($advance, $user, __('payroll.advance_delete_journal_note', ['id' => $advance->id]));

            $this->markDeleted($advance, $user, $reason, $note);

            activity()->performedOn($advance)->causedBy($user)->withProperties(['reason' => $reason, 'note' => $note])->log('payroll.advance_deleted');

            return $advance;
        });
    }
}
