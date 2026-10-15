<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    accounts: { type: Array, required: true },
    journals: { type: Array, required: true },
    trialBalance: { type: Object, required: true },
    profitLoss: { type: Object, required: true },
    balanceSheet: { type: Object, required: true },
    vatSummary: { type: Object, required: true },
    ledger: { type: Array, required: true },
    ledgerAccount: { type: String, required: true },
    cashFlow: { type: Object, required: true },
    payables: { type: Array, required: true },
    vouchers: { type: Array, required: true },
    statementLines: { type: Array, required: true },
    suppliers: { type: Array, required: true },
    fiscalYears: { type: Array, required: true },
    from: { type: String, required: true },
    to: { type: String, required: true },
});

const tab = ref('statements');
const journalOpen = ref(false);
const ledgerCode = ref(props.ledgerAccount);
const matchChoice = ref({});

const tabKeys = ['statements', 'ledger', 'cash_flow', 'payables', 'vouchers', 'reconcile', 'journals', 'accounts', 'fiscal_years'];

function tabLabel(key) {
    const labels = {
        statements: 'trial_balance',
        ledger: 'ledger',
        cash_flow: 'cash_flow',
        payables: 'payables',
        vouchers: 'vouchers',
        reconcile: 'reconcile',
        journals: 'journals',
        accounts: 'accounts',
        fiscal_years: 'fiscal_years',
    };

    return t('accounting.' + labels[key]);
}

const voucherForm = useForm({
    type: 'expense',
    amount: '',
    entry_date: new Date().toISOString().slice(0, 10),
    pay_from_code: '1100',
    expense_account_code: '5400',
    supplier_id: '',
    note: '',
    attachment: null,
});

const statementForm = useForm({
    account_code: '1100',
    occurred_on: new Date().toISOString().slice(0, 10),
    amount: '',
    description: '',
});

function loadLedger() {
    router.get(route('admin.accounting.index'), { account: ledgerCode.value, from: props.from, to: props.to }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function saveVoucher() {
    voucherForm.post(route('admin.accounting.vouchers.store'), {
        forceFormData: true,
        onSuccess: () => {
            voucherForm.reset();
            notify.success(t('accounting.voucher_saved'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function saveStatement() {
    statementForm.post(route('admin.accounting.statements.store'), {
        onSuccess: () => {
            statementForm.reset();
            notify.success(t('accounting.statement_saved'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function matchLine(line) {
    router.post(route('admin.accounting.statements.match', line.id), { journal_id: matchChoice.value[line.id] }, {
        preserveScroll: true,
        onSuccess: () => notify.success(t('accounting.statement_matched')),
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('accounting.match_amount')),
    });
}

function saveAccount(account) {
    router.put(route('admin.accounting.accounts.update', account.id), {
        name: account.name,
        is_active: account.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => notify.success(t('accounting.account_saved')),
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

const journalForm = useForm({ entry_date: new Date().toISOString().slice(0, 10), note: '', lines: [] });

function openJournal() {
    journalForm.defaults({
        entry_date: new Date().toISOString().slice(0, 10),
        note: '',
        lines: [
            { account_code: '', debit: '0.00', credit: '0.00' },
            { account_code: '', debit: '0.00', credit: '0.00' },
        ],
    }).reset();
    journalOpen.value = true;
}

function addLine() {
    journalForm.lines.push({ account_code: '', debit: '0.00', credit: '0.00' });
}

const journalDebit = computed(() => journalForm.lines.reduce((sum, line) => sum + Number(line.debit || 0), 0));
const journalCredit = computed(() => journalForm.lines.reduce((sum, line) => sum + Number(line.credit || 0), 0));

function saveJournal() {
    journalForm.post(route('admin.accounting.journals.store'), {
        onSuccess: () => {
            journalOpen.value = false;
            notify.success(t('accounting.journal_saved'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function approve(journal) {
    router.post(route('admin.accounting.journals.approve', journal.id), {}, {
        onSuccess: () => notify.success(t('accounting.journal_posted')),
        onError: () => notify.error(t('accounting.not_balanced_generic')),
    });
}

function reverse(journal) {
    router.post(route('admin.accounting.journals.reverse', journal.id), {}, {
        onSuccess: () => notify.success(t('accounting.journal_reversed')),
    });
}

function lockYear(year) {
    router.post(route('admin.accounting.years.lock', year.id), {}, {
        onSuccess: () => notify.success(t('accounting.year_locked')),
    });
}

function exportStatement(statement) {
    window.open(route('admin.accounting.export', statement) + `?from=${props.from}&to=${props.to}`, '_blank');
}
</script>

<template>
    <div>
        <PageHeader :title="t('accounting.title')" :description="t('accounting.subtitle')">
            <template #actions>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background" @click="openJournal">
                    <AppIcon name="plus" size="12" />
                    {{ t('accounting.new_journal') }}
                </button>
                <button type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-[13px] hover:bg-background" @click="exportStatement('trial-balance')">
                    <AppIcon name="file-export" size="12" />
                    {{ t('accounting.trial_balance') }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-3 flex gap-1 border-b border-border">
            <button
                v-for="key in tabKeys"
                :key="key"
                type="button"
                class="-mb-px border-b-2 px-3 py-1.5 text-[13px] font-medium"
                :class="tab === key ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'"
                @click="tab = key"
            >
                {{ tabLabel(key) }}
            </button>
        </div>

        <!-- Statements -->
        <div v-if="tab === 'statements'" class="grid gap-3 lg:grid-cols-2">
            <section class="overflow-hidden rounded-lg border border-border bg-surface">
                <header class="flex items-center justify-between border-b border-border px-3 py-2">
                    <h2 class="text-sm font-semibold">{{ t('accounting.trial_balance') }}</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="trialBalance.balanced ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger'">
                        {{ trialBalance.balanced ? t('accounting.balanced') : t('accounting.not_balanced_badge') }}
                    </span>
                </header>
                <div class="thin-scrollbar max-h-80 overflow-y-auto">
                    <table class="w-full text-[13px]">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="text-xs font-semibold text-muted">
                                <th class="px-3 py-2 text-left">{{ t('accounting.accounts') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('accounting.debit') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('accounting.credit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in trialBalance.rows" :key="row.code" class="border-t border-border/60">
                                <td class="px-3 py-1.5">{{ row.code }} - {{ row.name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ Number(row.debit) ? row.debit : '\u2013' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ Number(row.credit) ? row.credit : '\u2013' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-border font-semibold">
                                <td class="px-3 py-2">{{ t('common.total') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ trialBalance.debits }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ trialBalance.credits }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-border bg-surface p-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">{{ t('accounting.profit_loss') }}</h2>
                    <button type="button" class="text-xs text-muted hover:text-foreground" @click="exportStatement('profit-loss')">PDF</button>
                </div>
                <table class="mt-2 w-full text-[13px]">
                    <tbody>
                        <tr class="border-b border-border/60"><td class="py-1.5 text-muted">{{ t('accounting.revenue') }}</td><td class="py-1.5 text-right tabular-nums">{{ formatMoney(profitLoss.revenue) }}</td></tr>
                        <tr class="border-b border-border/60"><td class="py-1.5 text-muted">{{ t('accounting.cogs') }}</td><td class="py-1.5 text-right tabular-nums text-danger">{{ formatMoney(profitLoss.cogs) }}</td></tr>
                        <tr class="border-b-2 border-border font-semibold"><td class="py-1.5">{{ t('accounting.gross_profit') }}</td><td class="py-1.5 text-right tabular-nums">{{ formatMoney(profitLoss.gross_profit) }}</td></tr>
                        <tr v-for="expense in profitLoss.expenses" :key="expense.name" class="border-b border-border/60">
                            <td class="py-1.5 text-muted">{{ expense.name }}</td>
                            <td class="py-1.5 text-right tabular-nums text-danger">{{ formatMoney(expense.amount) }}</td>
                        </tr>
                        <tr class="border-t-2 border-border font-semibold">
                            <td class="py-2">{{ t('accounting.net_profit') }}</td>
                            <td class="py-2 text-right tabular-nums" :class="Number(profitLoss.net_profit) >= 0 ? 'text-success' : 'text-danger'">{{ formatMoney(profitLoss.net_profit) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-3 text-xs text-muted">{{ t('accounting.total_vat') }}: <span class="font-semibold text-foreground tabular-nums">{{ formatMoney(vatSummary.total_vat) }}</span></p>
            </section>
        </div>

        <section v-else-if="tab === 'ledger'" class="rounded-lg border border-border bg-surface">
            <div class="flex items-end gap-2 border-b border-border px-3 py-2">
                <FormField :label="t('accounting.accounts')">
                    <template #default="{ id }">
                        <select :id="id" v-model="ledgerCode" class="h-8 rounded-md border border-border bg-background px-2 text-[13px]">
                            <option v-for="account in accounts" :key="account.code" :value="account.code">{{ account.code }} - {{ account.name }}</option>
                        </select>
                    </template>
                </FormField>
                <button type="button" class="h-8 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground" @click="loadLedger">
                    {{ t('accounting.load_ledger') }}
                </button>
            </div>
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold text-muted">
                        <th class="px-3 py-2 text-left">{{ t('reports.date') }}</th>
                        <th class="px-3 py-2 text-left">{{ t('aggregators.platform_order_id') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.debit') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.credit') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in ledger" :key="index" class="border-b border-border/60">
                        <td class="px-3 py-1.5">{{ row.date }}</td>
                        <td class="px-3 py-1.5">{{ row.reference }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.debit }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.credit }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.balance }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else-if="tab === 'cash_flow'" class="max-w-md rounded-lg border border-border bg-surface p-3">
            <h2 class="text-sm font-semibold">{{ t('accounting.cash_flow') }}</h2>
            <table class="mt-2 w-full text-[13px]">
                <tbody>
                    <tr class="border-b border-border/60"><td class="py-1.5 text-muted">{{ t('accounting.inflow') }}</td><td class="py-1.5 text-right tabular-nums">{{ formatMoney(cashFlow.inflow) }}</td></tr>
                    <tr class="border-b border-border/60"><td class="py-1.5 text-muted">{{ t('accounting.outflow') }}</td><td class="py-1.5 text-right tabular-nums text-danger">{{ formatMoney(cashFlow.outflow) }}</td></tr>
                    <tr class="font-semibold"><td class="py-2">{{ t('accounting.net') }}</td><td class="py-2 text-right tabular-nums">{{ formatMoney(cashFlow.net) }}</td></tr>
                </tbody>
            </table>
        </section>

        <section v-else-if="tab === 'payables'" class="overflow-hidden rounded-lg border border-border bg-surface">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold text-muted">
                        <th class="px-3 py-2 text-left">{{ t('accounting.supplier') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.current') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.days_30') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.days_60') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.days_90') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('accounting.older') }}</th>
                        <th class="px-3 py-2 text-right">{{ t('common.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in payables" :key="row.supplier" class="border-b border-border/60">
                        <td class="px-3 py-1.5">{{ row.supplier }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.current }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.days_30 }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.days_60 }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.days_90 }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ row.older }}</td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums">{{ row.total }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else-if="tab === 'vouchers'" class="grid gap-3 lg:grid-cols-[280px_1fr]">
            <form novalidate class="space-y-2 rounded-lg border border-border bg-surface p-3" @submit.prevent="saveVoucher">
                <FormField :label="t('common.status')">
                    <template #default="{ id }">
                        <select :id="id" v-model="voucherForm.type" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                            <option value="expense">{{ t('accounting.voucher_expense') }}</option>
                            <option value="payment">{{ t('accounting.voucher_payment') }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField :label="t('reports.date')" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="voucherForm.entry_date" type="date" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                    </template>
                </FormField>
                <FormField :label="t('reports.amount')" required>
                    <template #default>
                        <MoneyInput v-model="voucherForm.amount" />
                    </template>
                </FormField>
                <FormField :label="t('accounting.pay_from')">
                    <template #default="{ id }">
                        <select :id="id" v-model="voucherForm.pay_from_code" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                            <option v-for="account in accounts.filter((item) => ['cash', 'bank', 'wallet'].includes(item.subtype))" :key="account.code" :value="account.code">{{ account.code }} - {{ account.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField v-if="voucherForm.type === 'expense'" :label="t('accounting.expenses')">
                    <template #default="{ id }">
                        <select :id="id" v-model="voucherForm.expense_account_code" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                            <option v-for="account in accounts.filter((item) => item.type === 'expense' || item.type === 'cogs')" :key="account.code" :value="account.code">{{ account.code }} - {{ account.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField v-else :label="t('accounting.supplier')" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="voucherForm.supplier_id" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                            <option value="">---</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField :label="t('common.note')">
                    <template #default="{ id }">
                        <input :id="id" v-model="voucherForm.note" type="text" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                    </template>
                </FormField>
                <FormField :label="t('accounting.attachment')">
                    <template #default="{ id }">
                        <input :id="id" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-[12px]" @change="voucherForm.attachment = $event.target.files[0]">
                    </template>
                </FormField>
                <button type="submit" class="h-8 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground" :disabled="voucherForm.processing">{{ t('common.save') }}</button>
            </form>
            <DataTable
                :rows="vouchers"
                :columns="[
                    { key: 'entry_date', label: t('reports.date'), align: 'left' },
                    { key: 'type', label: t('common.status') },
                    { key: 'amount', label: t('reports.amount'), type: 'money', align: 'right' },
                    { key: 'note', label: t('common.note'), align: 'left', truncate: true },
                ]"
            />
        </section>

        <section v-else-if="tab === 'reconcile'" class="grid gap-3 lg:grid-cols-[280px_1fr]">
            <form novalidate class="space-y-2 rounded-lg border border-border bg-surface p-3" @submit.prevent="saveStatement">
                <FormField :label="t('accounting.accounts')">
                    <template #default="{ id }">
                        <select :id="id" v-model="statementForm.account_code" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                            <option v-for="account in accounts.filter((item) => ['cash', 'bank', 'wallet'].includes(item.subtype))" :key="account.code" :value="account.code">{{ account.code }} - {{ account.name }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField :label="t('reports.date')" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="statementForm.occurred_on" type="date" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                    </template>
                </FormField>
                <FormField :label="t('reports.amount')" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="statementForm.amount" type="number" step="0.01" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px] tabular-nums">
                    </template>
                </FormField>
                <FormField :label="t('common.note')">
                    <template #default="{ id }">
                        <input :id="id" v-model="statementForm.description" type="text" class="h-8 w-full rounded-md border border-border bg-background px-2 text-[13px]">
                    </template>
                </FormField>
                <button type="submit" class="h-8 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground" :disabled="statementForm.processing">{{ t('common.save') }}</button>
            </form>
            <div class="overflow-hidden rounded-lg border border-border bg-surface">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-border text-xs font-semibold text-muted">
                            <th class="px-3 py-2 text-left">{{ t('reports.date') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('accounting.accounts') }}</th>
                            <th class="px-3 py-2 text-right">{{ t('reports.amount') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('accounting.journals') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in statementLines" :key="line.id" class="border-b border-border/60">
                            <td class="px-3 py-1.5">{{ String(line.occurred_on).slice(0, 10) }}</td>
                            <td class="px-3 py-1.5">{{ line.account_code }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ line.amount }}</td>
                            <td class="px-3 py-1.5">
                                <span v-if="line.journal_id" class="text-success">{{ line.journal_id }}</span>
                                <span v-else class="flex items-center gap-1">
                                    <select v-model="matchChoice[line.id]" class="h-7 rounded border border-border bg-background px-1 text-[12px]">
                                        <option value="">---</option>
                                        <option v-for="journal in journals.filter((item) => item.status === 'posted')" :key="journal.id" :value="journal.id">{{ journal.reference || journal.id }}</option>
                                    </select>
                                    <button type="button" class="h-7 rounded border border-border px-1.5 text-[11px]" @click="matchLine(line)">{{ t('accounting.match') }}</button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Journals -->
        <DataTable
            v-else-if="tab === 'journals'"
            :rows="journals"
            :columns="[
                { key: 'entry_date', label: t('reports.date'), align: 'left', minWidth: '112px' },
                { key: 'reference', label: t('aggregators.platform_order_id'), align: 'left' },
                { key: 'note', label: t('common.note'), align: 'left', truncate: true },
                { key: 'lines_count', label: t('accounting.journals'), type: 'number', minWidth: '64px' },
                { key: 'status', label: t('common.status'), minWidth: '104px' },
                { key: 'actions', label: '', align: 'right', type: 'component', width: '80px' },
            ]"
            searchable
        >
            <template #cell(entry_date)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value === 'posted' ? 'approved' : value === 'reversed' ? 'cancelled' : 'draft'" />
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex justify-end gap-1">
                    <button v-if="row.status === 'draft'" type="button" class="flex h-6 items-center rounded border border-success/50 px-1.5 text-[11px] text-success hover:bg-success/10" @click="approve(row)">
                        {{ t('accounting.approve') }}
                    </button>
                    <button v-if="row.status === 'posted'" type="button" class="flex h-6 items-center rounded border border-danger/40 px-1.5 text-[11px] text-danger hover:bg-danger/10" @click="reverse(row)">
                        {{ t('accounting.reverse') }}
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Chart of accounts -->
        <DataTable
            v-else-if="tab === 'accounts'"
            :rows="accounts"
            :columns="[
                { key: 'code', label: t('coupons.code'), align: 'left', sortable: true },
                { key: 'name', label: t('staff.name'), align: 'left', sortable: true },
                { key: 'is_active', label: t('accounting.active') },
                { key: 'actions', label: '', align: 'right', type: 'component', width: '80px' },
            ]"
            searchable
        >
            <template #cell(name)="{ row }">
                <input v-model="row.name" type="text" class="h-7 w-full rounded border border-border bg-background px-1.5 text-[13px]">
            </template>
            <template #cell(is_active)="{ row }">
                <input v-model="row.is_active" type="checkbox">
            </template>
            <template #cell(actions)="{ row }">
                <button type="button" class="h-6 rounded border border-border px-1.5 text-[11px]" @click="saveAccount(row)">{{ t('common.save') }}</button>
            </template>
        </DataTable>

        <!-- Fiscal years -->
        <section v-else-if="tab === 'fiscal_years'" class="overflow-hidden rounded-lg border border-border bg-surface">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold text-muted">
                        <th class="px-3 py-2 text-left">{{ t('accounting.fiscal_years') }}</th>
                        <th class="px-3 py-2 text-left">{{ t('reports.date') }}</th>
                        <th class="px-3 py-2">{{ t('common.status') }}</th>
                        <th class="px-3 py-2 text-right" style="width: 120px" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="year in fiscalYears" :key="year.id" class="border-b border-border/60">
                        <td class="px-3 py-1.5 font-medium">{{ year.name }}</td>
                        <td class="px-3 py-1.5">{{ year.starts_at?.slice(0, 10) }} - {{ year.ends_at?.slice(0, 10) }}</td>
                        <td class="px-3 py-1.5"><StatusBadge :status="year.is_locked ? 'closed' : 'open'" /></td>
                        <td class="px-3 py-1.5 text-right">
                            <button v-if="! year.is_locked" type="button" class="h-6 rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="lockYear(year)">
                                {{ t('accounting.lock_year') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Manual journal dialog -->
        <Teleport to="body">
            <div v-if="journalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="journalOpen = false">
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-base font-semibold">{{ t('accounting.new_journal') }}</h2>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <FormField :label="t('reports.date')" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="journalForm.entry_date" type="date" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm">
                            </template>
                        </FormField>
                        <FormField :label="t('common.note')">
                            <template #default="{ id }">
                                <input :id="id" v-model="journalForm.note" type="text" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm">
                            </template>
                        </FormField>
                    </div>

                    <div class="mt-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('accounting.journals') }}</h3>
                            <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px]" @click="addLine">
                                <AppIcon name="plus" size="10" />
                                {{ t('accounting.add_line') }}
                            </button>
                        </div>

                        <div v-for="(line, index) in journalForm.lines" :key="index" class="mb-2 grid grid-cols-[1.4fr_1fr_1fr_28px] items-center gap-2">
                            <select v-model="line.account_code" class="h-8 rounded-md border border-border bg-background px-1.5 text-[13px]">
                                <option value="" disabled>---</option>
                                <option v-for="account in accounts" :key="account.code" :value="account.code">{{ account.code }} - {{ account.name }}</option>
                            </select>
                            <MoneyInput v-model="line.debit" />
                            <MoneyInput v-model="line.credit" />
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" @click="journalForm.lines.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>

                        <div class="flex justify-between border-t border-border pt-2 text-sm" :class="Math.abs(journalDebit - journalCredit) < 0.01 ? 'text-success' : 'text-danger'">
                            <span>{{ t('accounting.debit') }}: <strong class="tabular-nums">{{ formatMoney(journalDebit) }}</strong></span>
                            <span>{{ t('accounting.credit') }}: <strong class="tabular-nums">{{ formatMoney(journalCredit) }}</strong></span>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm" @click="journalOpen = false">{{ t('common.cancel') }}</button>
                        <button
                            type="button"
                            :disabled="journalForm.processing || Math.abs(journalDebit - journalCredit) >= 0.01 || journalForm.lines.length < 2"
                            class="h-9 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            @click="saveJournal"
                        >
                            {{ t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
