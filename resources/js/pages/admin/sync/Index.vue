<script setup>
import { router } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    conflicts: { type: Array, required: true },
    recent: { type: Array, required: true },
});

const confirm = useConfirm();

const conflictRows = computed(() => props.conflicts.map((item) => ({
    ...item,
    conflict_reason_label: reasonLabel(item.conflict_reason),
    user_name: item.user?.name,
})));

function reasonLabel(reason) {
    const key = 'sync_conflicts.reasons.' + reason;

    return t(key) === key ? reason : t(key);
}

async function discard(item) {
    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('sync_conflicts.discard'),
        message: t('sync_conflicts.subtitle'),
        confirmText: t('sync_conflicts.discard'),
        reasons: ['other'],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('sync.discard', item.id), {}, {
        onSuccess: () => notify.success(t('sync_conflicts.conflict_discarded')),
    });
}

function retry(item) {
    router.post(route('sync.retry', item.id), {}, {
        onSuccess: () => notify.success(t('sync_conflicts.retried')),
        onError: () => notify.error(t('sync_conflicts.retry_failed')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('sync_conflicts.title')" :description="t('sync_conflicts.subtitle')" />

        <DataTable
            :rows="conflictRows"
            :columns="[
                { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
                { key: 'client_id', label: t('sync_conflicts.client_id'), align: 'left' },
                { key: 'type', label: t('sync_conflicts.type'), align: 'left' },
                { key: 'conflict_reason_label', label: t('sync_conflicts.reason'), align: 'left' },
                { key: 'user_name', label: t('staff.name'), align: 'left' },
                { key: 'actions', label: '', align: 'right', type: 'component', width: '120px' },
            ]"
            :empty-title="t('sync_conflicts.no_conflicts')"
        >
            <template #cell(created_at)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(conflict_reason_label)="{ value }">
                <span class="flex items-center gap-1.5 text-danger">
                    <AppIcon name="triangle-exclamation" size="12" />
                    {{ value }}
                </span>
            </template>
            <template #cell(actions)="{ row }">
                <div class="flex justify-end gap-1">
                    <button type="button" class="flex h-6 items-center rounded border border-border px-1.5 text-[11px] hover:bg-background" @click="retry(row)">
                        {{ t('sync_conflicts.retry') }}
                    </button>
                    <button type="button" class="flex h-6 items-center rounded border border-danger/40 px-1.5 text-[11px] text-danger hover:bg-danger/10" @click="discard(row)">
                        {{ t('sync_conflicts.discard') }}
                    </button>
                </div>
            </template>
        </DataTable>

        <section v-if="recent.length" class="mt-6">
            <h2 class="mb-2 text-sm font-semibold">{{ t('sync_conflicts.recent') }}</h2>

            <ul class="space-y-1">
                <li v-for="item in recent.slice(0, 10)" :key="item.id" class="flex items-center justify-between rounded-lg border border-border bg-surface px-3 py-1.5 text-[13px]">
                    <span class="font-mono text-xs text-muted">{{ item.client_id.slice(0, 18) }}</span>
                    <span>{{ item.type }}</span>
                    <StatusBadge status="approved" />
                </li>
            </ul>
        </section>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
