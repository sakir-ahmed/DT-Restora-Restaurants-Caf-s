<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Actions\Payroll\CalculateStaffPayAction;
use App\Enums\PayBasis;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\SalaryAdvance;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Wastage;
use Carbon\CarbonImmutable;

/**
 * Automatic postings from business events. Every method maps one
 * event to balanced journal lines through PostJournalEntryAction.
 * Account mapping lives here as configuration, not hard-coded ids
 * elsewhere.
 */
class AccountingService
{
    /**
     * Chart of accounts codes used by automatic postings.
     */
    private const CODES = [
        'cash' => '1100',
        'bkash' => '1110',
        'nagad' => '1120',
        'card' => '1130',
        'receivable' => '1200',
        'inventory' => '1300',
        'sales' => '4000',
        'sales_discount' => '4010',
        'service_charge' => '4020',
        'vat_payable' => '2100',
        'cogs' => '5000',
        'wastage' => '5100',
        'salary' => '5200',
        'salary_payable' => '2300',
        'commission' => '5300',
        'other_expense' => '5400',
        'payable' => '2200',
    ];

    public function __construct(
        private readonly PostJournalEntryAction $poster,
        private readonly CalculateStaffPayAction $pay,
    ) {}

    /**
     * Account codes used by automatic postings. Names may change; these codes may not.
     *
     * @return list<string>
     */
    public static function postingCodes(): array
    {
        return array_values(self::CODES);
    }

    /**
     * Post a paid order: revenue, VAT, COGS and the payment lines.
     */
    public function postOrderPaid(Order $order, ?User $user = null): void
    {
        if ($order->isVoid() || $order->payments()->where('status', 'completed')->doesntExist()) {
            return;
        }

        $already = Journal::query()
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->id)
            ->where('status', '!=', Journal::STATUS_REVERSED)
            ->exists();

        if ($already) {
            return;
        }

        $lines = [];

        $grossSales = round((float) $order->subtotal, 2);

        if ((float) $order->discount_amount > 0) {
            $lines[] = $this->line('sales_discount', debit: (float) $order->discount_amount);
        }

        if ($grossSales > 0) {
            $lines[] = $this->line('sales', credit: $grossSales);
        }

        if ((float) $order->extra_charge > 0) {
            $lines[] = $this->line('sales', credit: (float) $order->extra_charge);
        }

        if ((float) $order->service_charge > 0) {
            $lines[] = $this->line('service_charge', credit: (float) $order->service_charge);
        }

        if ((float) $order->vat_amount > 0) {
            $lines[] = $this->line('vat_payable', credit: (float) $order->vat_amount);
        }

        if ((float) $order->food_cost > 0) {
            $lines[] = $this->line('cogs', debit: (float) $order->food_cost);
            $lines[] = $this->line('inventory', credit: (float) $order->food_cost);
        }

        if ((float) $order->commission_amount > 0) {
            $lines[] = $this->line('commission', debit: (float) $order->commission_amount);
            $lines[] = $this->line('receivable', credit: (float) $order->commission_amount);
        }

        foreach ($order->payments()->where('status', 'completed')->get() as $payment) {
            $lines[] = $this->line($this->paymentAccount((string) $payment->method), debit: (float) $payment->amount);
        }

        $this->poster->execute(
            entryDate: $order->created_at->toDateString(),
            lines: $this->balance($lines, (float) $order->total),
            reference: $order->invoice_no,
            note: __('accounting.order_note', ['invoice' => $order->invoice_no]),
            source: $order,
            user: $user,
        );
    }

    /**
     * Void reverses the original journal.
     */
    public function postOrderVoided(Order $order, ?User $user = null): void
    {
        $original = Journal::query()
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->id)
            ->where('status', Journal::STATUS_POSTED)
            ->first();

        if ($original !== null) {
            $this->poster->reverse($original, $user);
        }
    }

    /**
     * Goods receipt posts inventory and the supplier payable.
     */
    public function postPurchaseReceived(Purchase $purchase, ?User $user = null): void
    {
        if ($purchase->status !== Purchase::STATUS_RECEIVED) {
            return;
        }

        $total = (float) $purchase->total;

        if ($total <= 0) {
            return;
        }

        $this->poster->execute(
            entryDate: $purchase->purchased_at->toDateString(),
            lines: [
                $this->line('inventory', debit: $total),
                $this->line('payable', credit: $total),
            ],
            reference: $purchase->invoice_no ?? 'PUR-'.$purchase->id,
            note: __('accounting.purchase_note', ['id' => $purchase->id]),
            source: $purchase,
            user: $user,
        );
    }

    /**
     * Wastage posts the cost out of inventory.
     */
    public function postWastage(Wastage $wastage, ?User $user = null): void
    {
        if ((float) $wastage->cost <= 0) {
            return;
        }

        $this->poster->execute(
            entryDate: $wastage->created_at->toDateString(),
            lines: [
                $this->line('wastage', debit: (float) $wastage->cost),
                $this->line('inventory', credit: (float) $wastage->cost),
            ],
            reference: 'WST-'.$wastage->id,
            note: __('accounting.wastage_note', ['id' => $wastage->id]),
            source: $wastage,
            user: $user,
        );
    }

    /**
     * Allocate one day of active salaries per branch. Idempotent by reference.
     */
    public function postDailySalary(\DateTimeInterface $date, ?User $user = null): void
    {
        $day = $date->format('Y-m-d');

        Branch::query()->where('is_active', true)->orderBy('id')->each(function (Branch $branch) use ($day, $user): void {
            $reference = 'SAL-'.$branch->code.'-'.str_replace('-', '', $day);

            $already = Journal::query()
                ->where('reference', $reference)
                ->where('status', '!=', Journal::STATUS_REVERSED)
                ->exists();

            if ($already) {
                return;
            }

            $monthly = (float) Employee::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->where('pay_basis', PayBasis::Monthly)
                ->sum('monthly_salary');

            $dayDate = CarbonImmutable::parse($day);
            $amount = round($monthly / 30 + $this->pay->attendanceCost($branch->id, $dayDate, $dayDate), 2);

            if ($amount <= 0) {
                return;
            }

            $this->poster->execute(
                entryDate: $day,
                lines: [
                    $this->line('salary', debit: $amount),
                    $this->line('salary_payable', credit: $amount),
                ],
                reference: $reference,
                note: __('accounting.salary_note', ['branch' => $branch->code, 'date' => $day]),
                user: $user,
            );
        });
    }

    /**
     * Expense voucher debits an expense account. Payment voucher reduces supplier payables.
     */
    public function postVoucher(Voucher $voucher, ?User $user = null): void
    {
        $already = Journal::query()
            ->where('source_type', $voucher->getMorphClass())
            ->where('source_id', $voucher->id)
            ->where('status', '!=', Journal::STATUS_REVERSED)
            ->exists();

        if ($already) {
            return;
        }

        $amount = (float) $voucher->amount;

        if ($amount <= 0) {
            return;
        }

        $lines = $voucher->type === Voucher::TYPE_PAYMENT
            ? [
                $this->line('payable', debit: $amount),
                ['account_code' => $voucher->pay_from_code, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['account_code' => $voucher->expense_account_code ?: self::CODES['other_expense'], 'debit' => $amount, 'credit' => 0],
                ['account_code' => $voucher->pay_from_code, 'debit' => 0, 'credit' => $amount],
            ];

        $journal = $this->poster->execute(
            entryDate: $voucher->entry_date->toDateString(),
            lines: $lines,
            reference: 'VCH-'.$voucher->id,
            note: $voucher->note ?: __('accounting.voucher_note', ['id' => $voucher->id]),
            source: $voucher,
            user: $user,
        );

        $voucher->update(['journal_id' => $journal->id]);
    }

    /**
     * Cash paid as a salary advance. Debits accrued salaries and credits cash
     * so the existing ledger stays balanced. The period pay calculation
     * subtracts the same advance from gross pay.
     */
    public function postSalaryAdvance(SalaryAdvance $advance, ?User $user = null): Journal
    {
        $existing = Journal::query()
            ->where('source_type', $advance->getMorphClass())
            ->where('source_id', $advance->id)
            ->where('status', '!=', Journal::STATUS_REVERSED)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $amount = (float) $advance->amount;
        $advance->loadMissing('employee');

        return $this->poster->execute(
            entryDate: $advance->advanced_on->toDateString(),
            lines: [
                $this->line('salary_payable', debit: $amount),
                $this->line('cash', credit: $amount),
            ],
            reference: 'ADV-'.$advance->id,
            note: __('accounting.advance_note', ['name' => $advance->employee->name ?? '']),
            source: $advance,
            user: $user,
        );
    }

    /**
     * Operating expense.
     */
    public function postExpense(Expense $expense, ?User $user = null): void
    {
        $this->poster->execute(
            entryDate: $expense->expense_date->toDateString(),
            lines: [
                $this->line('other_expense', debit: (float) $expense->amount),
                $this->line('cash', credit: (float) $expense->amount),
            ],
            reference: 'EXP-'.$expense->id,
            note: $expense->description,
            source: $expense,
            user: $user,
        );
    }

    /**
     * @return array{account_code: string, debit?: float, credit?: float}
     */
    private function line(string $key, float $debit = 0, float $credit = 0): array
    {
        return ['account_code' => self::CODES[$key], 'debit' => $debit, 'credit' => $credit];
    }

    private function paymentAccount(string $method): string
    {
        if (in_array($method, ['cash', 'bkash', 'nagad', 'card'], true)) {
            return $method;
        }

        // Methods an admin adds post to the account of their type: other wallets to the bKash
        // wallet account, bank POS terminals and the like to the card settlement account.
        return match (PaymentMethod::findByCode($method)?->type) {
            PaymentMethod::TYPE_WALLET => 'bkash',
            PaymentMethod::TYPE_CARD, PaymentMethod::TYPE_OTHER => 'card',
            default => 'cash',
        };
    }

    /**
     * Even out rounding drift on the payment side so the entry always
     * balances exactly.
     *
     * @param  array<int, array{account_code: string, debit?: float, credit?: float}>  $lines
     * @return array<int, array{account_code: string, debit?: float, credit?: float}>
     */
    private function balance(array $lines, float $target): array
    {
        $debits = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credits = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);

        $drift = round($debits - $credits, 2);

        if (abs($drift) > 0.001) {
            // A separate one-sided line. Never add the opposite amount
            // onto a line that already has a debit or a credit.
            if ($drift > 0) {
                $lines[] = $this->line('sales', credit: $drift);
            } else {
                $lines[] = $this->line('sales', debit: abs($drift));
            }
        }

        return $lines;
    }
}
