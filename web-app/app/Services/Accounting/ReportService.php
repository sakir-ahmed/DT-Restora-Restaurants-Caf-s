<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;
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
            ->whereDate('journals.entry_date', '>=', $from)
            ->whereDate('journals.entry_date', '<=', $to)
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
            ->whereDate('journals.entry_date', '>=', $from)
            ->whereDate('journals.entry_date', '<=', $to)
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
            ->whereDate('journals.entry_date', '<=', $asOf)
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
        // net_debit is debit minus credit, so income is negative and expenses are positive.
        $retained = round(-1 * (float) $sums->whereIn('type', ['income', 'expense', 'cogs'])->sum('net_debit'), 2);

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
            ->whereDate('journals.entry_date', '>=', $from)
            ->whereDate('journals.entry_date', '<=', $to)
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));

        return ['total_vat' => number_format($vat, 2, '.', '')];
    }

    /**
     * @return array<int, array{date: string, reference: ?string, debit: string, credit: string, balance: string}>
     */
    public function ledger(string $accountCode, string $from, string $to): array
    {
        $rows = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $accountCode)
            ->where('journals.status', 'posted')
            ->whereDate('journals.entry_date', '>=', $from)
            ->whereDate('journals.entry_date', '<=', $to)
            ->orderBy('journals.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journals.entry_date', 'journals.reference', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $balance = 0.0;

        return $rows->map(function ($row) use (&$balance) {
            $balance = round($balance + (float) $row->debit - (float) $row->credit, 2);

            return [
                'date' => substr((string) $row->entry_date, 0, 10),
                'reference' => $row->reference,
                'debit' => number_format((float) $row->debit, 2, '.', ''),
                'credit' => number_format((float) $row->credit, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
            ];
        })->all();
    }

    /**
     * Cash, bank and wallet movement for the period.
     *
     * @return array{inflow: string, outflow: string, net: string}
     */
    public function cashFlow(string $from, string $to): array
    {
        $row = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', 'posted')
            ->whereIn('accounts.subtype', ['cash', 'bank', 'wallet'])
            ->whereDate('journals.entry_date', '>=', $from)
            ->whereDate('journals.entry_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as inflow, COALESCE(SUM(journal_lines.credit), 0) as outflow')
            ->first();

        $inflow = round((float) ($row->inflow ?? 0), 2);
        $outflow = round((float) ($row->outflow ?? 0), 2);

        return [
            'inflow' => number_format($inflow, 2, '.', ''),
            'outflow' => number_format($outflow, 2, '.', ''),
            'net' => number_format($inflow - $outflow, 2, '.', ''),
        ];
    }

    /**
     * Unpaid received purchases, aged from the purchase date. Payments
     * (vouchers) reduce the oldest bills first.
     *
     * @return array<int, array{supplier: string, current: string, days_30: string, days_60: string, days_90: string, older: string, total: string}>
     */
    public function payablesAging(): array
    {
        $purchases = DB::table('purchases')
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.status', 'received')
            ->orderBy('purchases.purchased_at')
            ->orderBy('purchases.id')
            ->get(['purchases.supplier_id', 'suppliers.name', 'purchases.total', 'purchases.purchased_at']);

        $payments = DB::table('vouchers')
            ->where('type', 'payment')
            ->selectRaw('supplier_id, SUM(amount) as paid')
            ->groupBy('supplier_id')
            ->pluck('paid', 'supplier_id');

        $grouped = [];

        foreach ($purchases as $purchase) {
            $id = (int) $purchase->supplier_id;
            $grouped[$id] ??= [
                'supplier' => (string) $purchase->name,
                'bills' => [],
                'paid' => (float) ($payments[$id] ?? 0),
            ];
            $grouped[$id]['bills'][] = $purchase;
        }

        $rows = [];

        foreach ($grouped as $group) {
            $remainingPayment = $group['paid'];
            $buckets = ['current' => 0.0, 'days_30' => 0.0, 'days_60' => 0.0, 'days_90' => 0.0, 'older' => 0.0];

            foreach ($group['bills'] as $bill) {
                $open = (float) $bill->total;

                if ($remainingPayment > 0) {
                    $applied = min($remainingPayment, $open);
                    $open -= $applied;
                    $remainingPayment -= $applied;
                }

                if ($open <= 0) {
                    continue;
                }

                $purchasedOn = Carbon::parse($bill->purchased_at)->startOfDay();
                $age = max(0, (int) $purchasedOn->diffInDays(now()->startOfDay()));
                $key = match (true) {
                    $age <= 30 => 'current',
                    $age <= 60 => 'days_30',
                    $age <= 90 => 'days_60',
                    $age <= 120 => 'days_90',
                    default => 'older',
                };
                $buckets[$key] = round($buckets[$key] + $open, 2);
            }

            $total = round(array_sum($buckets), 2);

            if ($total <= 0) {
                continue;
            }

            $rows[] = [
                'supplier' => $group['supplier'],
                'current' => number_format($buckets['current'], 2, '.', ''),
                'days_30' => number_format($buckets['days_30'], 2, '.', ''),
                'days_60' => number_format($buckets['days_60'], 2, '.', ''),
                'days_90' => number_format($buckets['days_90'], 2, '.', ''),
                'older' => number_format($buckets['older'], 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
            ];
        }

        return $rows;
    }
}
