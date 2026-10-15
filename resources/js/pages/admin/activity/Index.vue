<script setup>
import { computed } from 'vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import { formatDateTime } from '@/lib/format';

const props = defineProps({
    entries: { type: Array, required: true },
});

const columns = computed(() => [
    { key: 'when', label: t('activity.when'), align: 'left', minWidth: '160px' },
    { key: 'causer', label: t('activity.who'), align: 'left' },
    { key: 'description', label: t('activity.what'), align: 'left' },
    { key: 'subject_type', label: t('activity.subject'), align: 'left' },
]);

const rows = computed(() => props.entries.map((entry) => ({
    ...entry,
    when: entry.created_at ? formatDateTime(entry.created_at) : '',
    causer: entry.causer ?? t('activity.system'),
})));
</script>

<template>
    <div>
        <PageHeader :title="t('nav.activity_log')" :description="t('activity.subtitle')" />
        <DataTable class="mt-3" :columns="columns" :rows="rows" :empty-title="t('activity.empty')" />
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
