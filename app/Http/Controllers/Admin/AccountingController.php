<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReportService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        $this->authorize('accounting.view');

        $from = (string) $request->input('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());
        $accountCode = (string) $request->input('account', '1100');

        if (Account::query()->where('code', $accountCode)->doesntExist()) {
            $accountCode = '1100';
        }

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
            'ledger' => $reports->ledger($accountCode, $from, $to),
            'ledgerAccount' => $accountCode,
            'cashFlow' => $reports->cashFlow($from, $to),
            'payables' => $reports->payablesAging(),
            'vouchers' => Voucher::query()->with('supplier:id,name')->latest('entry_date')->limit(50)->get(),
            'statementLines' => BankStatementLine::query()->latest('occurred_on')->limit(50)->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_at')->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function updateAccount(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('journals.post');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && in_array($account->code, AccountingService::postingCodes(), true)) {
            throw ValidationException::withMessages([
                'is_active' => __('accounting.account_required'),
            ]);
        }

        $account->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', __('accounting.account_saved'));
    }

    public function storeVoucher(Request $request, AccountingService $accounting): RedirectResponse
    {
        $this->authorize('vouchers.manage');

        $validated = $request->validate([
            'type' => ['required', Rule::in([Voucher::TYPE_EXPENSE, Voucher::TYPE_PAYMENT])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'entry_date' => ['required', 'date'],
            'pay_from_code' => ['required', Rule::in(['1100', '1110', '1120', '1130'])],
            'expense_account_code' => ['nullable', 'string', 'exists:accounts,code'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if ($validated['type'] === Voucher::TYPE_PAYMENT && empty($validated['supplier_id'])) {
            throw ValidationException::withMessages([
                'supplier_id' => __('accounting.supplier_required'),
            ]);
        }

        $expenseCode = null;

        if ($validated['type'] === Voucher::TYPE_EXPENSE) {
            $expenseCode = $validated['expense_account_code'] ?? '5400';
            $expenseAccount = Account::query()
                ->where('code', $expenseCode)
                ->whereIn('type', [Account::TYPE_EXPENSE, Account::TYPE_COGS])
                ->where('is_active', true)
                ->first();

            if ($expenseAccount === null) {
                throw ValidationException::withMessages([
                    'expense_account_code' => __('accounting.unknown_account', ['code' => $expenseCode]),
                ]);
            }

            $expenseCode = $expenseAccount->code;
        }

        $branchId = $request->user('staff')->branch_id ?? (int) session('branch_id', 0);

        if ($branchId < 1) {
            $branchId = Branch::query()->orderBy('id')->value('id');
        }

        $voucher = Voucher::query()->create([
            'branch_id' => $branchId,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'entry_date' => $validated['entry_date'],
            'pay_from_code' => $validated['pay_from_code'],
            'expense_account_code' => $expenseCode,
            'note' => $validated['note'] ?? null,
            'attachment_path' => $request->file('attachment')?->store('vouchers', 'local'),
            'user_id' => $request->user('staff')->id,
        ]);

        $accounting->postVoucher($voucher, $request->user('staff'));

        return back()->with('success', __('accounting.voucher_saved'));
    }

    public function storeStatement(Request $request): RedirectResponse
    {
        $this->authorize('journals.post');

        $validated = $request->validate([
            'account_code' => ['required', Rule::in(['1100', '1110', '1120', '1130'])],
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        BankStatementLine::query()->create([
            ...$validated,
            'user_id' => $request->user('staff')->id,
        ]);

        return back()->with('success', __('accounting.statement_saved'));
    }

    public function matchStatement(Request $request, BankStatementLine $line): RedirectResponse
    {
        $this->authorize('journals.post');

        if ($line->isMatched()) {
            throw ValidationException::withMessages([
                'journal_id' => __('accounting.already_matched'),
            ]);
        }

        $validated = $request->validate([
            'journal_id' => ['required', 'integer', 'exists:journals,id'],
        ]);

        $journal = Journal::query()->with('lines.account')->findOrFail($validated['journal_id']);

        if (! $journal->isPosted()) {
            throw ValidationException::withMessages([
                'journal_id' => __('accounting.match_posted'),
            ]);
        }

        $movement = round((float) $journal->lines
            ->filter(fn ($journalLine) => $journalLine->account?->code === $line->account_code)
            ->sum(fn ($journalLine) => (float) $journalLine->debit - (float) $journalLine->credit), 2);

        if (abs($movement - (float) $line->amount) > 0.001) {
            throw ValidationException::withMessages([
                'journal_id' => __('accounting.match_amount'),
            ]);
        }

        $line->update([
            'journal_id' => $journal->id,
            'matched_at' => now(),
        ]);

        activity()->performedOn($line)->causedBy($request->user('staff'))->log('statement.matched');

        return back()->with('success', __('accounting.statement_matched'));
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
