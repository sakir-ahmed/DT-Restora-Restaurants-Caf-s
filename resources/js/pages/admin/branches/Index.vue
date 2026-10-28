<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BranchPosSettings from '@/components/admin/BranchPosSettings.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    branches: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    defaultPos: { type: Object, required: true },
    layouts: { type: Array, default: () => [] },
    defaultPattern: { type: String, required: true },
});

const confirm = useConfirm();

const slideOverOpen = ref(false);
const editingId = ref(null);
const slideTab = ref('details');

const columns = computed(() => [
    { key: 'code', label: t('branches.code'), align: 'left', sortable: true, minWidth: '70px' },
    { key: 'name', label: t('branches.name'), align: 'left', sortable: true },
    { key: 'phone', label: t('branches.phone'), align: 'left' },
    { key: 'city', label: t('branches.city'), align: 'left', sortable: true },
    { key: 'vat_percent_display', label: t('branches.vat_percent'), type: 'number', sortable: true, minWidth: '80px' },
    { key: 'service_display', label: t('branches.service_charge'), type: 'number', sortable: true, minWidth: '80px' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

const rows = computed(() => props.branches.map((branch) => ({
    ...branch,
    vat_percent_display: Number(branch.vat_percent).toFixed(2) + '%',
    service_display: Number(branch.service_charge_percent).toFixed(2) + '%',
})));

const emptyForm = {
    name: '', name_bn: '', code: '', phone: '', address: '', address_bn: '', city: '', city_bn: '',
    latitude: '', longitude: '', vat_percent: '15.00', vat_inclusive: false,
    service_charge_percent: '0.00', allow_negative_stock: false,
    online_ordering_enabled: false, receipt_footer: '', is_active: true,
};

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function cashText(pos) {
    return (pos.cash_suggestions ?? []).join(', ');
}

const form = useForm({ ...emptyForm, pos: clone(props.defaultPos), cash_suggestions_text: cashText(props.defaultPos) });

const showPosErrors = computed(() => Object.keys(form.errors).some((key) => key.startsWith('pos')));

function openCreate() {
    editingId.value = null;
    form.defaults({ ...emptyForm, pos: clone(props.defaultPos), cash_suggestions_text: cashText(props.defaultPos) }).reset();
    form.clearErrors();
    slideTab.value = 'details';
    slideOverOpen.value = true;
}

function openEdit(branch) {
    editingId.value = branch.id;
    form.defaults({
        name: branch.name,
        name_bn: branch.name_bn ?? '',
        code: branch.code,
        phone: branch.phone ?? '',
        address: branch.address ?? '',
        address_bn: branch.address_bn ?? '',
        city: branch.city ?? '',
        city_bn: branch.city_bn ?? '',
        latitude: branch.latitude ?? '',
        longitude: branch.longitude ?? '',
        vat_percent: branch.vat_percent,
        vat_inclusive: branch.vat_inclusive,
        service_charge_percent: branch.service_charge_percent,
        allow_negative_stock: branch.allow_negative_stock,
        online_ordering_enabled: branch.online_ordering_enabled,
        receipt_footer: branch.receipt_footer ?? '',
        is_active: branch.is_active,
        pos: clone(branch.pos),
        cash_suggestions_text: cashText(branch.pos),
    }).reset();
    form.clearErrors();
    slideTab.value = 'details';
    slideOverOpen.value = true;
}

function save() {
    form.transform((data) => {
        const { cash_suggestions_text: suggestions, ...rest } = data;

        return {
            ...rest,
            pos: {
                ...rest.pos,
                cash_suggestions: suggestions.split(/[,\s]+/).filter(Boolean).map(Number).filter((value) => Number.isFinite(value)),
            },
        };
    });

    if (editingId.value === null) {
        form.post(route('admin.branches.store'), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('branches.created'));
            },
        });
    } else {
        form.put(route('admin.branches.update', editingId.value), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('branches.updated'));
            },
        });
    }
}

async function remove(branch) {
    const result = await confirm({
        variant: 'danger',
        level: 'typed',
        title: t('branches.delete_title', { code: branch.code }),
        message: t('branches.delete_message'),
        confirmText: t('branches.delete_confirm'),
        typedWord: branch.code,
        facts: [
            { label: t('branches.name'), value: branch.name },
            { label: t('branches.code'), value: branch.code },
        ],
    });

    if (! result.confirmed) {
        return;
    }

    form.delete(route('admin.branches.destroy', branch.id), {
        onSuccess: () => notify.success(t('branches.deleted')),
        onError: () => notify.error(t('branches.delete_not_empty')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('branches.title')" :description="t('branches.subtitle')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('branches.add') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :clickable="canManage"
            :empty-title="t('branches.empty')"
            :empty-description="t('branches.empty_hint')"
            :empty-action="canManage ? t('branches.add') : ''"
            @row-click="canManage && openEdit($event)"
            @empty-action="openCreate"
        >
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
            <template #cell(name)="{ row }">
                <span class="block truncate font-medium">{{ row.name }}</span>
                <span v-if="row.name_bn" class="block truncate text-xs text-muted">{{ row.name_bn }}</span>
            </template>
        </DataTable>

        <SlideOver
            :open="slideOverOpen"
            :title="editingId === null ? t('branches.add') : t('branches.edit')"
            :subtitle="t('branches.form_hint')"
            wide
            @close="slideOverOpen = false"
        >
            <div class="mb-3 flex gap-1" role="tablist">
                <button
                    v-for="entry in [{ key: 'details', label: t('branches.tab_details'), error: ! showPosErrors && Object.keys(form.errors).length > 0 }, { key: 'pos', label: t('branches.tab_pos'), error: showPosErrors }]"
                    :key="entry.key"
                    type="button"
                    role="tab"
                    :aria-selected="slideTab === entry.key"
                    class="h-7 rounded-md border px-3 text-[13px] font-medium"
                    :class="[slideTab === entry.key ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted hover:text-foreground', entry.error ? 'border-danger' : '']"
                    @click="slideTab = entry.key"
                >
                    {{ entry.label }}
                </button>
            </div>

            <BranchPosSettings
                v-show="slideTab === 'pos'"
                v-model:cash-text="form.cash_suggestions_text"
                :pos="form.pos"
                :errors="form.errors"
                :layouts="layouts"
                :default-pattern="defaultPattern"
                :branch-code="form.code"
            />

            <form v-show="slideTab === 'details'" novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('branches.name')" required :error="form.errors.name">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.name"
                                type="text"
                                class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                                :class="form.errors.name ? 'border-danger' : 'border-border focus:border-accent'"
                            >
                        </template>
                    </FormField>

                    <FormField :label="t('branches.name_bn')" :error="form.errors.name_bn">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.name_bn"
                                type="text"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                            >
                        </template>
                    </FormField>

                    <FormField :label="t('branches.code')" required :error="form.errors.code" :helper="t('branches.code_helper')">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.code"
                                type="text"
                                placeholder="BR01"
                                class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                                :class="form.errors.code ? 'border-danger' : 'border-border focus:border-accent'"
                            >
                        </template>
                    </FormField>

                    <FormField :label="t('branches.phone')" :error="form.errors.phone">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.phone"
                                type="tel"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] tabular-nums text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                            >
                        </template>
                    </FormField>

                    <div class="col-span-2">
                        <FormField :label="t('branches.address')" :error="form.errors.address">
                            <template #default="{ id }">
                                <input
                                    :id="id"
                                    v-model="form.address"
                                    type="text"
                                    class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                                >
                            </template>
                        </FormField>
                    </div>

                    <div class="col-span-2">
                        <FormField :label="t('branches.address_bn')" :error="form.errors.address_bn">
                            <template #default="{ id }">
                                <input
                                    :id="id"
                                    v-model="form.address_bn"
                                    type="text"
                                    class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                                >
                            </template>
                        </FormField>
                    </div>

                    <FormField :label="t('branches.city')" :error="form.errors.city">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.city"
                                type="text"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                            >
                        </template>
                    </FormField>

                    <FormField :label="t('branches.city_bn')" :error="form.errors.city_bn">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.city_bn"
                                type="text"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                            >
                        </template>
                    </FormField>

                    <FormField :label="t('branches.receipt_footer')" :error="form.errors.receipt_footer">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="form.receipt_footer"
                                type="text"
                                class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40"
                            >
                        </template>
                    </FormField>
                </div>

                <div class="border-t border-border pt-3">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted" :class="$page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''">
                        {{ t('branches.settings_group') }}
                    </h3>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <FormField :label="t('branches.vat_percent')" required :error="form.errors.vat_percent">
                            <MoneyInput v-model="form.vat_percent" prefix="%" />
                        </FormField>

                        <FormField :label="t('branches.service_charge')" required :error="form.errors.service_charge_percent">
                            <MoneyInput v-model="form.service_charge_percent" prefix="%" />
                        </FormField>
                    </div>

                    <div class="mt-3 space-y-2">
                        <SwitchField v-model="form.vat_inclusive" :label="t('branches.vat_inclusive')" />
                        <SwitchField v-model="form.allow_negative_stock" :label="t('branches.allow_negative_stock')" />
                        <SwitchField v-model="form.online_ordering_enabled" :label="t('branches.online_ordering_enabled')" />
                        <SwitchField v-model="form.is_active" :label="t('branches.is_active')" />
                    </div>
                </div>
            </form>

            <template #footer>
                <button
                    type="button"
                    class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background"
                    @click="slideOverOpen = false"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60"
                    @click="save"
                >
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
