<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    suppliers: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const slideOverOpen = ref(false);
const editingId = ref(null);

const columns = computed(() => [
    { key: 'name', label: t('inventory.supplier'), align: 'left', sortable: true },
    { key: 'mobile', label: t('staff.mobile'), align: 'left' },
    { key: 'email', label: t('staff.email'), align: 'left' },
    { key: 'address', label: t('branches.address'), align: 'left', truncate: true },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

const form = useForm({
    name: '', name_bn: '', mobile: '', email: '', address: '', is_active: true,
});

function openCreate() {
    editingId.value = null;
    form.defaults({ name: '', name_bn: '', mobile: '', email: '', address: '', is_active: true }).reset();
    slideOverOpen.value = true;
}

function openEdit(supplier) {
    editingId.value = supplier.id;
    form.defaults({
        name: supplier.name,
        name_bn: supplier.name_bn ?? '',
        mobile: supplier.mobile ?? '',
        email: supplier.email ?? '',
        address: supplier.address ?? '',
        is_active: supplier.is_active,
    }).reset();
    slideOverOpen.value = true;
}

function save() {
    if (editingId.value === null) {
        form.post(route('admin.suppliers.store'), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('inventory.supplier_created'));
            },
        });
    } else {
        form.put(route('admin.suppliers.update', editingId.value), {
            onSuccess: () => {
                slideOverOpen.value = false;
                notify.success(t('inventory.supplier_updated'));
            },
        });
    }
}

async function remove(supplier) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('inventory.supplier_deleted_title', { name: supplier.name }),
        message: t('common.confirm'),
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    form.delete(route('admin.suppliers.destroy', supplier.id), {
        onSuccess: () => notify.success(t('inventory.supplier_deleted')),
        onError: () => notify.error(t('inventory.supplier_delete_not_empty')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.suppliers')" :description="t('inventory.no_suppliers_hint')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openCreate"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('inventory.add_supplier') }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="suppliers"
            searchable
            :clickable="canManage"
            :empty-title="t('inventory.no_suppliers')"
            :empty-description="t('inventory.no_suppliers_hint')"
            :empty-action="canManage ? t('inventory.add_supplier') : ''"
            @row-click="canManage && openEdit($event)"
            @empty-action="openCreate"
        >
            <template #cell(name)="{ row }">
                <span class="block truncate font-medium">{{ row.name }}</span>
                <span v-if="row.name_bn" class="block truncate text-xs text-muted">{{ row.name_bn }}</span>
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
        </DataTable>

        <SlideOver :open="slideOverOpen" :title="editingId === null ? t('inventory.add_supplier') : t('inventory.edit_supplier')" @close="slideOverOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('inventory.supplier')" required :error="form.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="form.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('branches.name_bn')" :error="form.errors.name_bn">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.name_bn" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')" :error="form.errors.mobile">
                        <MobileInput v-model="form.mobile" />
                    </FormField>

                    <FormField :label="t('staff.email')" :error="form.errors.email">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.email" type="email" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <div class="col-span-2">
                        <FormField :label="t('branches.address')" :error="form.errors.address">
                            <template #default="{ id }">
                                <input :id="id" v-model="form.address" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                            </template>
                        </FormField>
                    </div>
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
