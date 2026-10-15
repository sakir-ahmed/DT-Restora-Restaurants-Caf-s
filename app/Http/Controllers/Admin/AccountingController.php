<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Services\Accounting\ReportService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        $this->authorize('accounting.view');

        $from = (string) $request->input('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        return Inertia::render('admin/accounting/Index', [
            'accounts' => Account::query()->orderBy('code')->get(),
            'journals' => Journal::query()
                ->with(['lines.account:id,code,name', 'creator:id,name'])
                ->withCount('lines')
                ->latest('entry_date')
                ->limit(50)
                ->get(),
            'trialBalance' => $reports->trialBalance($from, $to),
            'profitLoss' => $reports->profitAndLoss($from, $to),
            'balanceSheet' => $reports->balanceSheet($to),
            'vatSummary' => $reports->vatSummary($from, $to),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_at')->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function storeManual(Request $request, PostJournalEntryAction $action): RedirectResponse
    {
        $this->authorize('journals.post');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'exists:accounts,code'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->execute(
            entryDate: $validated['entry_date'],
            lines: $validated['lines'],
            note: $validated['note'] ?? null,
            user: $request->user('staff'),
            draft: ! $request->user('staff')->can('journals.post'),
        );

        return back()->with('success', __('accounting.journal_saved'));
    }

    public function approve(Request $request, Journal $journal, PostJournalEntryAction $action): RedirectResponse
    {
        $this->authorize('journals.post');

        $journal = Journal::query()->whereKey($journal->id)->lockForUpdate()->firstOrFail();

        if ($journal->status !== Journal::STATUS_DRAFT) {
            return back()->with('error', __('accounting.only_draft_can_approve'));
        }

        if (abs($journal->totalDebit() - $journal->totalCredit()) > 0.001) {
            return back()->with('error', __('accounting.not_balanced_generic'));
        }

        $journal->update([
            'status' => Journal::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => $request->user('staff')->id,
        ]);

        activity()->performedOn($journal)->causedBy($request->user('staff'))->log('journal.approved');

        return back()->with('success', __('accounting.journal_posted'));
    }

    public function reverse(Request $request, Journal $journal, PostJournalEntryAction $action): RedirectResponse
    {
        $this->authorize('journals.post');

        $action->reverse($journal, $request->user('staff'));

        return back()->with('success', __('accounting.journal_reversed'));
    }

    public function lockYear(Request $request, FiscalYear $year): RedirectResponse
    {
        $this->authorize('periods.lock');

        $year->update([
            'is_locked' => true,
            'locked_at' => now(),
        ]);

        activity()->performedOn($year)->causedBy($request->user('staff'))->log('fiscal_year.locked');

        return back()->with('success', __('accounting.year_locked'));
    }

    public function export(Request $request, string $statement, ReportService $reports)
    {
        $this->authorize('accounting.view');

        $from = (string) $request->input('from', now()->startOfYear()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        $data = match ($statement) {
            'trial-balance' => ['title' => __('accounting.trial_balance'), 'data' => $reports->trialBalance($from, $to)],
            'profit-loss' => ['title' => __('accounting.profit_loss'), 'data' => $reports->profitAndLoss($from, $to)],
            'balance-sheet' => ['title' => __('accounting.balance_sheet'), 'data' => $reports->balanceSheet($to)],
            'vat-summary' => ['title' => __('accounting.vat_summary'), 'data' => $reports->vatSummary($from, $to)],
            default => abort(404),
        };

        $pdf = app(PDF::class);
        $pdf->loadView('reports.accounting', [
            'title' => $data['title'],
            'statement' => $statement,
            'data' => $data['data'],
            'from' => $from,
            'to' => $to,
        ]);

        return $pdf->download($statement.'-'.$from.'-'.$to.'.pdf');
    }
}
