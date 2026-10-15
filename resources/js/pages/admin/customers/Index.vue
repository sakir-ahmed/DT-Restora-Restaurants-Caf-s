<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    customers: { type: Array, required: true },
    search: { type: String, default: '' },
});

const columns = computed(() => [
    { key: 'name', label: t('customers.name'), align: 'left', sortable: true },
    { key: 'mobile', label: t('customers.mobile'), align: 'left' },
    { key: 'orders_count', label: t('customers.orders'), type: 'number' },
    { key: 'points', label: t('customers.points'), type: 'number' },
]);

let searchTimer = null;

function searchCustomers(value) {
    if (value === props.search) {
        return;
    }

    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.customers.index'), { search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
}
</script>

<template>
    <div>
        <PageHeader :title="t('nav.customers')" :description="t('customers.subtitle')" />

        <DataTable
            searchable
            :filter-locally="false"
            :initial-search="search"
            :search-placeholder="t('customers.search')"
            :columns="columns"
            :rows="customers"
            :empty-title="t('customers.empty')"
            @search="searchCustomers"
        />
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
