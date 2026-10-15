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
    fiscalYears: { type: Array, required: true },
    from: { type: String, required: true },
    to: { type: String, required: true },
});

const tab = ref('statements');
const journalOpen = ref(false);

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
    journalForm.post(route('accounting.journals.store'), {
        onSuccess: () => {
            journalOpen.value = false;
            notify.success(t('accounting.journal_saved'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function approve(journal) {
    router.post(route('accounting.journals.approve', journal.id), {}, {
        onSuccess: () => notify.success(t('accounting.journal_posted')),
        onError: () => notify.error(t('accounting.not_balanced_generic')),
    });
}

function reverse(journal) {
    router.post(route('accounting.journals.reverse', journal.id), {}, {
        onSuccess: () => notify.success(t('accounting.journal_reversed')),
    });
}

function lockYear(year) {
    router.post(route('accounting.years.lock', year.id), {}, {
        onSuccess: () => notify.success(t('accounting.year_locked')),
    });
}

function exportStatement(statement) {
    window.open(route('accounting.export', statement) + `?from=${props.from}&to=${props.to}`, '_blank');
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
                v-for="key in ['statements', 'journals', 'accounts', 'fiscal_years']"
                :key="key"
                type="button"
                class="-mb-px border-b-2 px-3 py-1.5 text-[13px] font-medium"
                :class="tab === key ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'"
                @click="tab = key"
            >
                {{ t('accounting.' + (key === 'statements' ? 'trial_balance' : key === 'journals' ? 'journals' : key === 'accounts' ? 'accounts' : 'fiscal_years')) }}
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
                { key: 'type', label: t('common.status') },
            ]"
            searchable
        />

        <!-- Fiscal years -->
        <section v-else class="overflow-hidden rounded-lg border border-border bg-surface">
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
