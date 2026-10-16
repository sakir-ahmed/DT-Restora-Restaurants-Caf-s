<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    tables: { type: Array, required: true },
    branchName: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const slideOverOpen = ref(false);
const editingId = ref(null);
const editingStatus = ref('free');
const qrUrl = ref('');

const inputClass = 'h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40';

const columns = computed(() => [
    { key: 'name', label: t('tables.name'), align: 'left', sortable: true },
    { key: 'area', label: t('tables.area'), align: 'left', sortable: true },
    { key: 'seats', label: t('tables.seats'), type: 'number', sortable: true, minWidth: '64px' },
    { key: 'status', label: t('tables.status'), minWidth: '104px' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
    { key: 'actions', label: '', align: 'right', type: 'component', width: '64px' },
]);

const rows = computed(() => props.tables);

const emptyForm = () => ({
    is_range: false,
    name: '',
    prefix: 'T',
    from: 1,
    to: 10,
    area: '',
    seats: 4,
    status: 'free',
    is_active: true,
});

const form = useForm(emptyForm());

const slideTitle = computed(() => (editingId.value === null ? t('tables.add') : t('tables.edit')));

function openCreate() {
    editingId.value = null;
    editingStatus.value = 'free';
    qrUrl.value = '';
    form.defaults(emptyForm()).reset();
    form.clearErrors();
    slideOverOpen.value = true;
}

function openEdit(table) {
    editingId.value = table.id;
    editingStatus.value = table.status;
    qrUrl.value = table.qr_url;
    form.defaults({
        is_range: false,
        name: table.name,
        prefix: 'T',
        from: 1,
        to: 10,
        area: table.area ?? '',
        seats: table.seats,
        status: table.status === 'occupied' ? 'occupied' : table.status,
        is_active: table.is_active,
    }).reset();
    form.clearErrors();
    slideOverOpen.value = true;
}

function payload() {
    const shared = {
        area: form.area || null,
        seats: Number(form.seats),
        is_active: form.is_active,
    };

    if (editingId.value === null && form.is_range) {
        return {
            ...shared,
            is_range: true,
            prefix: form.prefix,
            from: Number(form.from),
            to: Number(form.to),
        };
    }

    return {
        ...shared,
        is_range: false,
        name: form.name,
        ...(editingId.value !== null && editingStatus.value !== 'occupied' ? { status: form.status } : {}),
    };
}

function save() {
    const options = {
        data: payload(),
        preserveScroll: true,
        onSuccess: (page) => {
            slideOverOpen.value = false;
            notify.success(page.props.flash?.success ?? t('tables.updated'));
        },
    };

    if (editingId.value === null) {
        form.post(route('admin.tables.store'), options);

        return;
    }

    form.put(route('admin.tables.update', editingId.value), options);
}

async function remove(table) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('tables.delete_title'),
        message: t('tables.delete_message'),
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    form.delete(route('admin.tables.destroy', table.id), {
        preserveScroll: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('tables.deleted')),
    });
}

async function copyQr(url) {
    try {
        await navigator.clipboard.writeText(url);
        notify.success(t('tables.qr_copied'));
    } catch {
        notify.error(t('tables.qr_copy_failed'));
    }
}
</script>

<template>
    <div>
        <PageHeader :title="t('tables.title')" :description="t('tables.subtitle', { branch: branchName })">
            <template v-if="canManage" #actions>
                <button
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('tables.add') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="rows"
            searchable
            :clickable="canManage"
            :empty-title="t('tables.empty')"
            :empty-description="t('tables.empty_hint')"
            :empty-action="canManage ? t('tables.add') : ''"
            @row-click="openEdit"
            @empty-action="openCreate"
        >
            <template #cell(status)="{ value }">
                <StatusBadge :status="value" />
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-background hover:text-foreground"
                        :aria-label="t('tables.copy_qr')"
                        @click.stop="copyQr(row.qr_url)"
                    >
                        <AppIcon name="qrcode" size="12" />
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-danger/10 hover:text-danger"
                        :aria-label="t('common.delete')"
                        @click.stop="remove(row)"
                    >
                        <AppIcon name="trash" size="12" />
                    </button>
                </div>
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="slideTitle" @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div v-if="editingId === null" class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="h-7 rounded-md border text-[13px] font-medium"
                        :class="form.is_range ? 'border-border bg-surface text-muted' : 'border-accent bg-accent/10 text-foreground'"
                        @click="form.is_range = false"
                    >
                        {{ t('tables.single') }}
                    </button>
                    <button
                        type="button"
                        class="h-7 rounded-md border text-[13px] font-medium"
                        :class="form.is_range ? 'border-accent bg-accent/10 text-foreground' : 'border-border bg-surface text-muted'"
                        @click="form.is_range = true"
                    >
                        {{ t('tables.range') }}
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div v-if="! form.is_range || editingId !== null" class="col-span-2">
                        <FormField :label="t('tables.name')" required :error="form.errors.name">
                            <template #default="{ id }">
                                <input :id="id" v-model="form.name" type="text" maxlength="50" :class="[inputClass, form.errors.name ? 'border-danger' : 'border-border focus:border-accent']">
                            </template>
                        </FormField>
                    </div>

                    <template v-if="form.is_range && editingId === null">
                        <div class="col-span-2">
                            <FormField :label="t('tables.prefix')" required :error="form.errors.prefix">
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.prefix" type="text" maxlength="20" :class="[inputClass, form.errors.prefix ? 'border-danger' : 'border-border focus:border-accent']">
                                </template>
                            </FormField>
                        </div>
                        <FormField :label="t('tables.from')" required :error="form.errors.from">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.from" type="number" min="1" max="500" :class="[inputClass, form.errors.from ? 'border-danger' : 'border-border focus:border-accent']">
                            </template>
                        </FormField>
                        <FormField :label="t('tables.to')" required :error="form.errors.to">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.to" type="number" min="1" max="500" :class="[inputClass, form.errors.to ? 'border-danger' : 'border-border focus:border-accent']">
                            </template>
                        </FormField>
                    </template>

                    <FormField :label="t('tables.area')" :error="form.errors.area">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.area" type="text" maxlength="50" :class="[inputClass, form.errors.area ? 'border-danger' : 'border-border focus:border-accent']">
                        </template>
                    </FormField>

                    <FormField :label="t('tables.seats')" required :error="form.errors.seats">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="form.seats" type="number" min="1" max="30" :class="[inputClass, form.errors.seats ? 'border-danger' : 'border-border focus:border-accent']">
                        </template>
                    </FormField>

                    <div v-if="editingId !== null && editingStatus !== 'occupied'" class="col-span-2">
                        <FormField :label="t('tables.status')" :error="form.errors.status">
                            <template #default="{ id }">
                                <select :id="id" v-model="form.status" :class="[inputClass, 'border-border focus:border-accent']">
                                    <option value="free">{{ t('tables.free') }}</option>
                                    <option value="reserved">{{ t('tables.reserved') }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>

                    <p v-if="editingStatus === 'occupied'" class="col-span-2 text-[13px] text-muted">
                        {{ t('tables.occupied_locked') }}
                    </p>

                    <div class="col-span-2">
                        <SwitchField v-model="form.is_active" :label="t('tables.active')" />
                    </div>

                    <div v-if="qrUrl" class="col-span-2">
                        <FormField :label="t('tables.qr')">
                            <template #default="{ id }">
                                <div class="flex gap-2">
                                    <input :id="id" :value="qrUrl" type="text" readonly class="h-7 min-w-0 flex-1 rounded-md border border-border bg-background px-2 text-[13px] text-foreground">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background"
                                        @click="copyQr(qrUrl)"
                                    >
                                        <AppIcon name="qrcode" size="12" />
                                        {{ t('tables.copy_qr') }}
                                    </button>
                                </div>
                            </template>
                        </FormField>
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="slideOverOpen = false">
                    {{ t('common.cancel') }}
                </button>
                <button
                    v-if="canManage"
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
