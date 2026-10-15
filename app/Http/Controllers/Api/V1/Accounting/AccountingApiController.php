<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Voucher;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingApiController extends Controller
{
    use InteractsWithApi;

    public function accounts(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'accounting.view');

        return $this->success(Account::query()->orderBy('code')->get());
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'journals.post');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:asset,liability,equity,income,expense,cogs'],
        ]);

        $account = Account::query()->create([...$validated, 'is_active' => true]);

        return $this->success($account, null, 201);
    }

    public function updateAccount(Request $request, Account $account): JsonResponse
    {
        $this->permit($this->staffUser($request), 'journals.post');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && in_array($account->code, AccountingService::postingCodes(), true)) {
            abort(422, __('accounting.account_required'));
        }

        $account->update($validated);

        return $this->success($account->fresh());
    }

    public function journals(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'accounting.view');

        return $this->success(Journal::query()->with('lines')->latest('entry_date')->limit(50)->get());
    }

    public function storeJournal(Request $request, PostJournalEntryAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'journals.post');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $journal = $action->execute(
            entryDate: $validated['entry_date'],
            lines: $validated['lines'],
            note: $validated['note'] ?? null,
            user: $user,
        );

        return $this->success(['id' => $journal->id], null, 201);
    }

    public function approveJournal(Request $request, Journal $journal): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'journals.post');
        abort_unless($journal->status === Journal::STATUS_DRAFT, 422, __('accounting.only_draft_can_approve'));

        $journal->update([
            'status' => Journal::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => $user->id,
        ]);

        return $this->success(['id' => $journal->id, 'status' => $journal->status]);
    }

    public function reverseJournal(Request $request, Journal $journal, PostJournalEntryAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'journals.post');
        $reversal = $action->reverse($journal, $user);

        return $this->success(['id' => $reversal->id]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'vouchers.manage');

        return $this->success(Voucher::query()->latest('entry_date')->limit(50)->get());
    }

    public function storeVoucher(Request $request, AccountingService $accounting): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'vouchers.manage');

        $validated = $request->validate([
            'type' => ['required', Rule::in([Voucher::TYPE_EXPENSE, Voucher::TYPE_PAYMENT])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'entry_date' => ['required', 'date'],
            'pay_from_code' => ['required', Rule::in(['1100', '1110', '1120', '1130'])],
            'expense_account_code' => ['nullable', 'string'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['type'] === Voucher::TYPE_PAYMENT && empty($validated['supplier_id'])) {
            abort(422, __('accounting.supplier_required'));
        }

        $voucher = Voucher::query()->create([
            'branch_id' => $user->branch_id,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'entry_date' => $validated['entry_date'],
            'pay_from_code' => $validated['pay_from_code'],
            'expense_account_code' => $validated['type'] === Voucher::TYPE_EXPENSE
                ? ($validated['expense_account_code'] ?? '5400')
                : null,
            'note' => $validated['note'] ?? null,
            'user_id' => $user->id,
        ]);

        $accounting->postVoucher($voucher, $user);

        return $this->success(['id' => $voucher->id, 'journal_id' => $voucher->fresh()->journal_id], null, 201);
    }

    public function payables(Request $request, ReportService $reports): JsonResponse
    {
        $this->permit($this->staffUser($request), 'accounting.view');

        return $this->success($reports->payablesAging());
    }

    public function lockPeriod(Request $request, FiscalYear $period): JsonResponse
    {
        $this->permit($this->staffUser($request), 'periods.lock');
        $period->update(['is_locked' => true, 'locked_at' => now()]);

        return $this->success(['id' => $period->id, 'is_locked' => true]);
    }

    public function statement(Request $request, string $statement, ReportService $reports): JsonResponse
    {
        $this->permit($this->staffUser($request), 'accounting.view');
        $from = (string) $request->input('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        $data = match ($statement) {
            'trial-balance' => $reports->trialBalance($from, $to),
            'ledger' => $reports->ledger((string) $request->input('account', '1100'), $from, $to),
            'profit-loss' => $reports->profitAndLoss($from, $to),
            'balance-sheet' => $reports->balanceSheet($to),
            'cash-flow' => $reports->cashFlow($from, $to),
            'vat-summary' => $reports->vatSummary($from, $to),
            default => abort(404),
        };

        return $this->success($data);
    }
}
