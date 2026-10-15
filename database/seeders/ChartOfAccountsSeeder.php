<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets
            ['code' => '1100', 'name' => 'Cash in hand', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1110', 'name' => 'bKash wallet', 'type' => 'asset', 'subtype' => 'wallet'],
            ['code' => '1120', 'name' => 'Nagad wallet', 'type' => 'asset', 'subtype' => 'wallet'],
            ['code' => '1130', 'name' => 'SSLCommerz settlement', 'type' => 'asset', 'subtype' => 'bank'],
            ['code' => '1200', 'name' => 'Receivables', 'type' => 'asset', 'subtype' => 'receivable'],
            ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'subtype' => 'inventory'],
            // Liabilities
            ['code' => '2100', 'name' => 'VAT payable', 'type' => 'liability', 'subtype' => 'vat'],
            ['code' => '2200', 'name' => 'Supplier payables', 'type' => 'liability', 'subtype' => 'payable'],
            // Equity
            ['code' => '3100', 'name' => 'Owner capital', 'type' => 'equity', 'subtype' => null],
            // Income
            ['code' => '4000', 'name' => 'Sales revenue', 'type' => 'income', 'subtype' => 'sales'],
            ['code' => '4010', 'name' => 'Sales discounts', 'type' => 'income', 'subtype' => 'discount'],
            ['code' => '4020', 'name' => 'Service charge income', 'type' => 'income', 'subtype' => 'service_charge'],
            // COGS and expenses
            ['code' => '5000', 'name' => 'Cost of goods sold', 'type' => 'cogs', 'subtype' => null],
            ['code' => '5100', 'name' => 'Wastage', 'type' => 'expense', 'subtype' => 'wastage'],
            ['code' => '5200', 'name' => 'Salaries', 'type' => 'expense', 'subtype' => 'salary'],
            ['code' => '5300', 'name' => 'Aggregator commission', 'type' => 'expense', 'subtype' => 'commission'],
            ['code' => '5400', 'name' => 'Operating expenses', 'type' => 'expense', 'subtype' => 'other'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(['code' => $account['code']], $account);
        }

        FiscalYear::query()->updateOrCreate(
            ['name' => (string) now()->year],
            [
                'starts_at' => now()->startOfYear()->toDateString(),
                'ends_at' => now()->endOfYear()->toDateString(),
                'is_locked' => false,
            ],
        );
    }
}
