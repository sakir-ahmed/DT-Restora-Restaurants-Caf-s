<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import QrCode from '@/components/QrCode.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { qrMatrix } from '@/lib/qr';

const props = defineProps({
    tables: { type: Array, required: true },
    branchName: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const slideOverOpen = ref(false);
const editingId = ref(null);
const qrBusy = ref(false);

const inputClass = 'h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40';

const columns = computed(() => [
    { key: 'name', label: t('tables.name'), align: 'left', sortable: true },
    { key: 'area', label: t('tables.area'), align: 'left', sortable: true },
    { key: 'seats', label: t('tables.seats'), type: 'number', sortable: true, minWidth: '64px' },
    { key: 'state', label: t('tables.state'), minWidth: '104px' },
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
    is_active: true,
});

const form = useForm(emptyForm());

// The table whose details are open. It follows the page data, so a freshly issued QR code shows at once.
const editingTable = computed(() => props.tables.find((entry) => entry.id === editingId.value) ?? null);

const slideTitle = computed(() => (editingId.value === null ? t('tables.add') : t('tables.edit')));

function openCreate() {
    editingId.value = null;
    form.defaults(emptyForm()).reset();
    form.clearErrors();
    slideOverOpen.value = true;
}

function openEdit(table) {
    editingId.value = table.id;
    form.defaults({
        is_range: false,
        name: table.name,
        prefix: 'T',
        from: 1,
        to: 10,
        area: table.area ?? '',
        seats: table.seats,
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
    };
}

function save() {
    // useForm sends its own fields, so the cleaned-up payload goes in through transform.
    form.transform(() => payload());

    const options = {
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

function qrIssued(table) {
    return table?.qr_generated_at ? new Date(table.qr_generated_at).toLocaleString() : '';
}

async function regenerateQr(table) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('tables.qr_regenerate_title', { table: table.name }),
        message: t('tables.qr_regenerate_message'),
        confirmText: t('tables.qr_regenerate'),
    });

    if (! result.confirmed) {
        return;
    }

    qrBusy.value = true;

    router.post(route('admin.tables.qr.regenerate', table.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            notify.success(page.props.flash?.success ?? t('tables.qr_regenerated', { table: table.name }));
        },
        onFinish: () => {
            qrBusy.value = false;
        },
    });
}

async function regenerateAllQr() {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('tables.qr_regenerate_all_title'),
        message: t('tables.qr_regenerate_all_message'),
        confirmText: t('tables.qr_regenerate_all'),
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.tables.qr.regenerate-all'), {}, {
        preserveScroll: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('tables.qr_regenerated_all', { count: props.tables.length })),
    });
}

/**
 * Save the code as a PNG with a quiet zone, ready to drop into a print layout.
 */
function downloadQr(table) {
    const { size, modules } = qrMatrix(table.qr_url);
    const border = 4;
    const scale = 12;
    const canvas = document.createElement('canvas');

    canvas.width = (size + border * 2) * scale;
    canvas.height = canvas.width;

    const context = canvas.getContext('2d');

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#1a1208';
    modules.forEach((row, y) => row.forEach((dark, x) => {
        if (dark) {
            context.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
        }
    }));

    const link = document.createElement('a');

    link.href = canvas.toDataURL('image/png');
    link.download = `qr-${table.name}.png`;
    link.click();
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
            <template #actions>
                <Link
                    :href="route('admin.tables.qr-sheet')"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background"
                >
                    <AppIcon name="print" size="12" />
                    {{ t('tables.qr_print_sheet') }}
                </Link>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background"
                    @click="regenerateAllQr"
                >
                    <AppIcon name="rotate" size="12" />
                    {{ t('tables.qr_regenerate_all') }}
                </button>
                <button
                    v-if="canManage"
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
            <template #cell(state)="{ value }">
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
                        :aria-label="t('tables.qr_view')"
                        :title="t('tables.qr_view')"
                        @click.stop="openEdit(row)"
                    >
                        <AppIcon name="qrcode" size="12" />
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-muted hover:bg-background hover:text-foreground"
                        :aria-label="t('tables.qr_regenerate')"
                        :title="t('tables.qr_regenerate')"
                        @click.stop="regenerateQr(row)"
                    >
                        <AppIcon name="rotate" size="12" />
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
                <section v-if="editingTable" class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-background p-3" :aria-label="t('tables.qr_title')">
                    <div class="shrink-0 rounded-lg border border-border bg-white p-1.5">
                        <QrCode :value="editingTable.qr_url" :size="124" :border="1" :label="`${t('tables.qr_title')} ${editingTable.name}`" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div>
                            <p class="text-[13px] font-semibold text-foreground">{{ t('tables.qr_title') }}</p>
                            <p class="text-xs text-muted">{{ t('tables.qr_generated', { when: qrIssued(editingTable) }) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2 text-xs text-foreground hover:bg-background" @click="copyQr(editingTable.qr_url)">
                                <AppIcon name="copy" size="11" />
                                {{ t('tables.copy_qr') }}
                            </button>
                            <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2 text-xs text-foreground hover:bg-background" @click="downloadQr(editingTable)">
                                <AppIcon name="download" size="11" />
                                {{ t('tables.qr_download') }}
                            </button>
                            <a :href="editingTable.qr_url" target="_blank" rel="noopener" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2 text-xs text-foreground hover:bg-background">
                                <AppIcon name="arrow-up-right" size="11" />
                                {{ t('tables.qr_open') }}
                            </a>
                            <button v-if="canManage" type="button" :disabled="qrBusy" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-danger/40 bg-surface px-2 text-xs text-danger hover:bg-danger/10 disabled:opacity-60" @click="regenerateQr(editingTable)">
                                <AppIcon name="rotate" size="11" />
                                {{ t('tables.qr_regenerate') }}
                            </button>
                        </div>
                        <p class="text-[11px] text-muted">{{ t('tables.qr_hint') }}</p>
                    </div>
                </section>

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

                    <div class="col-span-2">
                        <SwitchField v-model="form.is_active" :label="t('tables.active')" />
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
