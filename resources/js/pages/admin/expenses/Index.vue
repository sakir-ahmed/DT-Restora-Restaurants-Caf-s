<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    expenses: { type: Array, required: true },
    categories: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const slideOverOpen = ref(false);

const columns = computed(() => [
    { key: 'expense_date', label: t('expenses.date'), align: 'left', sortable: true, minWidth: '112px' },
    { key: 'category_label', label: t('expenses.category'), align: 'left', sortable: true },
    { key: 'description', label: t('expenses.description'), align: 'left', truncate: true },
    { key: 'amount', label: t('expenses.amount'), type: 'money', sortable: true, minWidth: '96px' },
    { key: 'user_name', label: t('staff.name'), align: 'left' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '40px' },
]);

const rows = computed(() => props.expenses.map((expense) => ({
    ...expense,
    category_label: t('expenses.categories.' + expense.category) === 'expenses.categories.' + expense.category
        ? expense.category
        : t('expenses.categories.' + expense.category),
    user_name: expense.user?.name,
    sort_amount: Number(expense.amount),
})));

const total = computed(() => rows.value.reduce((sum, row) => sum + row.sort_amount, 0));

const form = useForm({
    expense_date: new Date().toISOString().slice(0, 10),
    category: 'utilities',
    description: '',
    amount: '0.00',
});

function openCreate() {
    form.defaults({
        expense_date: new Date().toISOString().slice(0, 10),
        category: 'utilities',
        description: '',
        amount: '0.00',
    }).reset();
    slideOverOpen.value = true;
}

function save() {
    form.post(route('admin.expenses.store'), {
        onSuccess: () => {
            slideOverOpen.value = false;
            notify.success(t('expenses.recorded'));
        },
    });
}

async function remove(expense) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('expenses.delete_title'),
        message: t('expenses.delete_message'),
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    form.delete(route('admin.expenses.destroy', expense.id), {
        onSuccess: () => notify.success(t('expenses.deleted')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('expenses.title')" :description="t('expenses.subtitle')">
            <template #actions>
                <button
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('expenses.add') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :empty-title="t('expenses.empty')"
            :totals-row="{ label: t('common.total'), amount: formatMoney(total) }"
        >
            <template #cell(expense_date)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(amount)="{ value }">
                <span class="text-danger">{{ formatMoney(value) }}</span>
            </template>
            <template #cell(actions)="{ row }">
                <button
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                    :aria-label="t('common.delete')"
                    @click="remove(row)"
                >
                    <AppIcon name="trash" size="12" />
                </button>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('expenses.add')" @close="slideOverOpen = false">
            <form class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('expenses.date')" required :error="form.errors.expense_date">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.expense_date" type="date" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.expense_date ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('expenses.category')" required :error="form.errors.category">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.category" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40">
                                <option v-for="category in categories" :key="category" :value="category">{{ t('expenses.categories.' + category) === 'expenses.categories.' + category ? category : t('expenses.categories.' + category) }}</option>
                            </select>
                        </template>
                    </FormField>

                    <div class="col-span-2">
                        <FormField :label="t('expenses.description')" :error="form.errors.description">
                            <template #default="{ id }">
                                <input :id="id" v-model="form.description" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                            </template>
                        </FormField>
                    </div>

                    <FormField :label="t('expenses.amount')" required :error="form.errors.amount">
                        <MoneyInput v-model="form.amount" />
                    </FormField>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="save">
                    <AppIcon v-if="form.processing" name="spinner" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
