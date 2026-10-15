<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    transfers: { type: Array, required: true },
    branches: { type: Array, required: true },
    ingredients: { type: Array, required: true },
    units: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const slideOverOpen = ref(false);

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'from_name', label: t('inventory.from_branch'), align: 'left' },
    { key: 'to_name', label: t('inventory.to_branch'), align: 'left' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '40px' },
]);

const rows = computed(() => props.transfers.map((transfer) => ({
    ...transfer,
    from_name: transfer.from_branch?.code + ' - ' + transfer.from_branch?.name,
    to_name: transfer.to_branch?.code + ' - ' + transfer.to_branch?.name,
    items_count: transfer.items?.length ?? 0,
})));

const form = useForm({
    from_branch_id: '',
    to_branch_id: '',
    note: '',
    items: [],
});

function openCreate() {
    form.defaults({
        from_branch_id: props.branches[0]?.id ?? '',
        to_branch_id: '',
        note: '',
        items: [],
    }).reset();
    addLine();
    slideOverOpen.value = true;
}

function addLine() {
    form.items.push({ ingredient_id: '', unit_id: '', qty: '1' });
}

function unitsFor(line) {
    const ingredient = props.ingredients.find((i) => i.id === Number(line.ingredient_id));

    if (! ingredient) {
        return props.units;
    }

    const sameType = props.units.filter((unit) => unit.type === ingredient.unit?.type);

    return sameType.length ? sameType : props.units;
}

function save() {
    form.post(route('admin.stock-transfers.store'), {
        onSuccess: () => {
            slideOverOpen.value = false;
            notify.success(t('inventory.transfer_requested'));
        },
        onError: (errors) => {
            if (errors.to_branch_id) {
                notify.error(errors.to_branch_id);
            }
        },
    });
}

function approve(transfer) {
    router.post(route('admin.stock-transfers.approve', transfer.id), {}, {
        onSuccess: () => notify.success(t('inventory.transfer_approved')),
    });
}

function receive(transfer) {
    router.post(route('admin.stock-transfers.receive', transfer.id), {}, {
        onSuccess: () => notify.success(t('inventory.transfer_received')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.transfers')" :description="t('inventory.transfers_subtitle')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('inventory.new_transfer') }}
                </button>
            </template>
        </PageHeader>

        <DataTable :columns="columns" :rows="rows" :empty-title="t('inventory.no_transfers')">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value === 'received' ? 'approved' : value" />
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex justify-end gap-1">
                    <button
                        v-if="row.status === 'requested'"
                        type="button"
                        class="flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background"
                        @click="approve(row)"
                    >
                        <AppIcon name="check" size="10" />
                        {{ t('inventory.approve_transfer') }}
                    </button>
                    <button
                        v-if="row.status === 'approved'"
                        type="button"
                        class="flex h-6 items-center gap-1 rounded bg-primary px-1.5 text-[11px] font-medium text-primary-foreground"
                        @click="receive(row)"
                    >
                        <AppIcon name="download" size="10" />
                        {{ t('inventory.receive_transfer') }}
                    </button>
                </div>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="t('inventory.new_transfer')" wide @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.from_branch')" required :error="form.errors.from_branch_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.from_branch_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.from_branch_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} - {{ branch.name }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('inventory.to_branch')" required :error="form.errors.to_branch_id">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.to_branch_id" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.to_branch_id ? 'border-danger' : 'border-border focus:border-accent'">
                                <option value="" disabled>{{ t('common.select') }}</option>
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} - {{ branch.name }}</option>
                            </select>
                        </template>
                    </FormField>
                </div>

                <div class="border-t border-border pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">{{ t('inventory.items_label') }}</h3>
                        <button type="button" class="inline-flex h-6 items-center gap-1 rounded border border-border px-1.5 text-[11px] text-foreground hover:bg-background" @click="addLine">
                            <AppIcon name="plus" size="10" />
                            {{ t('inventory.add_line') }}
                        </button>
                    </div>

                    <div v-for="(line, index) in form.items" :key="index" class="mb-2 grid grid-cols-[1.6fr_0.8fr_0.8fr_28px] items-start gap-2">
                        <FormField :error="form.errors['items.' + index + '.ingredient_id']">
                            <template #default="{ id }">
                                <select :id="id" v-model="line.ingredient_id" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none focus:border-accent">
                                    <option value="" disabled>{{ t('common.select') }}</option>
                                    <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :error="form.errors['items.' + index + '.unit_id']">
                            <template #default="{ id }">
                                <select :id="id" v-model="line.unit_id" class="h-7 w-full rounded-md border border-border bg-surface px-1 text-[12px] text-foreground outline-none focus:border-accent">
                                    <option value="" disabled>---</option>
                                    <option v-for="unit in unitsFor(line)" :key="unit.id" :value="unit.id">{{ unit.symbol }}</option>
                                </select>
                            </template>
                        </FormField>

                        <FormField :error="form.errors['items.' + index + '.qty']">
                            <template #default="{ id }">
                                <input :id="id" v-model="line.qty" type="number" step="0.001" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                            </template>
                        </FormField>

                        <div class="flex h-7 items-center">
                            <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger" :aria-label="t('common.remove')" @click="form.items.splice(index, 1)">
                                <AppIcon name="xmark" size="12" />
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="form.processing || form.items.length === 0" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="save">
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
