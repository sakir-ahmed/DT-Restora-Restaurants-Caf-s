<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import KpiCard from '@/components/ui-kit/KpiCard.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';

const page = usePage();

defineProps({
    setup: { type: Object, required: true },
});

const firstName = computed(() => (page.props.auth?.user?.name ?? '').split(' ')[0]);

const checklist = computed(() => [
    { label: t('dashboard.setup.branch'), done: true, icon: 'building' },
    { label: t('dashboard.setup.menu'), done: false, icon: 'book-open' },
    { label: t('dashboard.setup.ingredients'), done: false, icon: 'boxes-stacked' },
    { label: t('dashboard.setup.recipes'), done: false, icon: 'list-check' },
    { label: t('dashboard.setup.staff'), done: (page.props.auth?.user ?? null) !== null, icon: 'user-shield' },
]);
</script>

<template>
    <div>
        <PageHeader
            :title="t('dashboard.welcome', { name: firstName })"
            :description="t('dashboard.subtitle')"
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <KpiCard :label="t('dashboard.gross_sales')" value="0" loading />
            <KpiCard :label="t('dashboard.net_sales')" value="0" loading />
            <KpiCard :label="t('dashboard.orders')" value="0" loading />
        </div>

        <div class="mt-4 rounded-lg border border-border bg-surface p-4">
            <EmptyState
                v-if="false"
                icon="chart-line"
                :title="t('dashboard.empty_title')"
            />

            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                <AppIcon name="clipboard-list" size="14" class="text-accent" />
                {{ t('dashboard.setup.title') }}
            </h2>

            <ul class="space-y-1.5">
                <li
                    v-for="item in checklist"
                    :key="item.label"
                    class="flex items-center gap-2 rounded-md border border-border bg-background/50 px-3 py-2 text-[13px]"
                >
                    <span
                        class="flex h-5 w-5 items-center justify-center rounded-full"
                        :class="item.done ? 'bg-success/15 text-success' : 'bg-muted/10 text-muted'"
                    >
                        <AppIcon :name="item.done ? 'check' : item.icon" size="10" />
                    </span>
                    <span :class="item.done ? 'text-muted line-through' : 'text-foreground'">{{ item.label }}</span>
                </li>
            </ul>

            <p class="mt-3 text-xs text-muted">{{ t('dashboard.setup.hint') }}</p>
        </div>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
