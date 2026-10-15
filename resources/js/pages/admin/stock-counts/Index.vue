<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    counts: { type: Array, required: true },
    openCount: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();

const counted = ref({});
const saving = ref(false);

const columns = computed(() => [
    { key: 'created_at', label: t('ui_kit.created'), align: 'left', minWidth: '112px' },
    { key: 'items_count', label: t('inventory.items_label'), type: 'number', minWidth: '64px' },
    { key: 'status', label: t('common.status'), minWidth: '104px' },
    { key: 'closed_at', label: t('inventory.close_count'), align: 'left' },
]);

const rows = computed(() => props.counts.map((count) => ({
    ...count,
    closed_at: count.closed_at ? count.closed_at : null,
})));

async function startCount() {
    router.post(route('admin.stock-counts.store'), {}, {
        onSuccess: () => notify.success(t('inventory.count_started')),
        onError: () => notify.error(t('inventory.count_already_open')),
    });
}

function saveCounts() {
    saving.value = true;

    router.put(route('admin.stock-counts.update', props.openCount.id), { counted: counted.value }, {
        onSuccess: () => notify.success(t('inventory.count_saved')),
        onFinish: () => (saving.value = false),
    });
}

async function closeCount() {
    const result = await confirm({
        variant: 'warning',
        level: 'reason',
        title: t('inventory.close_count') + '?',
        message: t('inventory.count_close_message'),
        confirmText: t('inventory.close_count'),
        reasons: ['other'],
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.stock-counts.close', props.openCount.id), {}, {
        onSuccess: () => notify.success(t('inventory.count_closed')),
    });
}

function varianceOf(item) {
    const value = counted.value[item.id];

    if (value === undefined || value === '') {
        return item.variance_qty !== null ? Number(item.variance_qty) : null;
    }

    return Number(value) - Number(item.system_qty);
}
</script>

<template>
    <div>
        <PageHeader :title="t('inventory.counts')" :description="t('inventory.counts_subtitle')">
            <template #actions>
                <button
                    v-if="canManage && ! openCount"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="startCount"
                >
                    <AppIcon name="clipboard-list" size="12" />
                    {{ t('inventory.start_count') }}
                </button>
            </template>
        </PageHeader>

        <!-- Open count sheet -->
        <section v-if="openCount" class="mb-4 overflow-hidden rounded-lg border border-border bg-surface">
            <header class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <AppIcon name="list-check" size="14" class="text-accent" />
                    {{ t('inventory.counts') }} #{{ openCount.id }}
                </h2>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background"
                        @click="saveCounts"
                    >
                        <AppIcon v-if="saving" name="spinner" spin />
                        {{ t('inventory.save_counts') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                        @click="closeCount"
                    >
                        <AppIcon name="check" size="12" />
                        {{ t('inventory.close_count') }}
                    </button>
                </div>
            </header>

            <div class="thin-scrollbar max-h-[60vh] overflow-y-auto">
                <table class="w-full text-[13px]">
                    <thead class="sticky top-0 bg-surface">
                        <tr class="border-b border-border text-xs font-semibold text-muted">
                            <th class="px-3 py-2 text-left">{{ t('inventory.ingredient') }}</th>
                            <th class="px-3 py-2 text-right" style="min-width: 96px">{{ t('inventory.system_qty') }}</th>
                            <th class="px-3 py-2 text-right" style="min-width: 96px">{{ t('inventory.counted_qty') }}</th>
                            <th class="px-3 py-2 text-right" style="min-width: 80px">{{ t('inventory.variance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in openCount.items" :key="item.id" class="border-b border-border/60">
                            <td class="px-3 py-1.5 text-foreground">
                                {{ item.ingredient?.name }}
                                <span class="ml-1 text-xs text-muted">{{ item.ingredient?.unit?.symbol }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-foreground">{{ Number(item.system_qty) }}</td>
                            <td class="px-3 py-1.5 text-right">
                                <input
                                    v-model="counted[item.id]"
                                    type="number"
                                    step="0.001"
                                    :placeholder="item.counted_qty !== null ? String(Number(item.counted_qty)) : ''"
                                    class="h-6 w-24 rounded border border-border bg-background px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent"
                                >
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums" :class="varianceOf(item) !== null && varianceOf(item) < 0 ? 'text-danger' : varianceOf(item) > 0 ? 'text-success' : 'text-muted'">
                                {{ varianceOf(item) === null ? '\u2013' : varianceOf(item) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <DataTable :columns="columns" :rows="rows" :empty-title="t('inventory.no_counts')">
            <template #cell(created_at)="{ value }">
                <DateText :value="value" />
            </template>
            <template #cell(status)="{ value }">
                <StatusBadge :status="value === 'open' ? 'open' : 'closed'" />
            </template>
            <template #cell(closed_at)="{ value }">
                <DateText v-if="value" :value="value" />
                <span v-else class="text-muted">&ndash;</span>
            </template>
        </DataTable>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
