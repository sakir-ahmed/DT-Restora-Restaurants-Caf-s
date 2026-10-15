<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

/**
 * Financial statements computed from the journal lines.
 */
class ReportService
{
    /**
     * @return array{debits: string, credits: string, balanced: bool, rows: array<int, array{code: string, name: string, type: string, debit: string, credit: string}>}
     */
    public function trialBalance(string $from, string $to): array
    {
        $rows = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->whereBetween('journals.entry_date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.code as code, accounts.name as name, accounts.type as type')
            ->selectRaw('SUM(journal_lines.debit) as debit_sum')
            ->selectRaw('SUM(journal_lines.credit) as credit_sum')
            ->get();

        $debits = round((float) $rows->sum('debit_sum'), 2);
        $credits = round((float) $rows->sum('credit_sum'), 2);

        return [
            'debits' => number_format($debits, 2, '.', ''),
            'credits' => number_format($credits, 2, '.', ''),
            'balanced' => abs($debits - $credits) < 0.001,
            'rows' => $rows->map(fn ($row) => [
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'debit' => number_format((float) $row->debit_sum, 2, '.', ''),
                'credit' => number_format((float) $row->credit_sum, 2, '.', ''),
            ])->all(),
        ];
    }

    /**
     * @return array{revenue: string, cogs: string, gross_profit: string, expenses: array<int, array{name: string, amount: string}>, total_expenses: string, net_profit: string}
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $sums = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->whereBetween('journals.entry_date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw('accounts.code as code, accounts.name as name, accounts.type as type')
            ->selectRaw('SUM(journal_lines.credit) - SUM(journal_lines.debit) as net_credit')
            ->get();

        $revenue = round((float) $sums->where('type', 'income')->sum('net_credit'), 2);
        $cogs = round(abs((float) $sums->where('type', 'cogs')->sum('net_credit')), 2);

        $expenses = $sums->where('type', 'expense')
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'amount' => number_format(abs((float) $row->net_credit), 2, '.', ''),
            ])
            ->values();

        $totalExpenses = round((float) $expenses->sum(fn ($row) => (float) $row['amount']), 2);

        $grossProfit = round($revenue - $cogs, 2);
        $netProfit = round($grossProfit - $totalExpenses, 2);

        return [
            'revenue' => number_format($revenue, 2, '.', ''),
            'cogs' => number_format($cogs, 2, '.', ''),
            'gross_profit' => number_format($grossProfit, 2, '.', ''),
            'expenses' => $expenses->all(),
            'total_expenses' => number_format($totalExpenses, 2, '.', ''),
            'net_profit' => number_format($netProfit, 2, '.', ''),
        ];
    }

    /**
     * @return array{assets: array<int, array{name: string, amount: string}>, liabilities: array<int, array{name: string, amount: string}>, equity: array<int, array{name: string, amount: string}>, total_assets: string, total_liabilities_equity: string}
     */
    public function balanceSheet(string $asOf): array
    {
        $sums = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->where('journals.entry_date', '<=', $asOf)
            ->groupBy('accounts.name', 'accounts.type')
            ->selectRaw('accounts.name as name, accounts.type as type')
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as net_debit')
            ->get();

        $assets = $sums->where('type', 'asset')->map(fn ($row) => [
            'name' => (string) $row->name,
            'amount' => number_format((float) $row->net_debit, 2, '.', ''),
        ])->values();

        $liabilities = $sums->where('type', 'liability')->map(fn ($row) => [
            'name' => (string) $row->name,
            'amount' => number_format(abs((float) $row->net_debit), 2, '.', ''),
        ])->values();

        $equity = $sums->where('type', 'equity')->map(fn ($row) => [
            'name' => (string) $row->name,
            'amount' => number_format(abs((float) $row->net_debit), 2, '.', ''),
        ])->values();

        // Retained earnings from income minus expenses closes the sheet.
        $retained = round((float) $sums->whereIn('type', ['income', 'expense', 'cogs'])->sum('net_credit'), 2);

        if (abs($retained) > 0.001) {
            $equity->push(['name' => __('accounting.retained_earnings'), 'amount' => number_format($retained, 2, '.', '')]);
        }

        $totalAssets = round((float) $assets->sum('amount'), 2);
        $totalLiab = round((float) $liabilities->sum('amount') + (float) $equity->sum('amount'), 2);

        return [
            'assets' => $assets->all(),
            'liabilities' => $liabilities->all(),
            'equity' => $equity->all(),
            'total_assets' => number_format($totalAssets, 2, '.', ''),
            'total_liabilities_equity' => number_format($totalLiab, 2, '.', ''),
        ];
    }

    /**
     * @return array{total_vat: string}
     */
    public function vatSummary(string $from, string $to): array
    {
        $vat = (float) DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->where('accounts.subtype', 'vat')
            ->whereBetween('journals.entry_date', [$from, $to])
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));

        return ['total_vat' => number_format($vat, 2, '.', '')];
    }
}
