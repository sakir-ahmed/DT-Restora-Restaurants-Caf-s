<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import MenuBook from '@/components/preorder/MenuBook.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { localized } from '@/lib/site';

const props = defineProps({
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    tables: { type: Array, default: () => [] },
    preorders: { type: Array, default: () => [] },
    shareUrl: { type: String, required: true },
    placed: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const tab = ref('book');
const pendingCount = computed(() => props.preorders.filter((entry) => entry.status === 'pending').length);

function lineName(line) {
    return localized(line, 'name');
}

function summary(entry) {
    return entry.items.map((line) => `${line.quantity} x ${lineName(line)}`).join(', ');
}

function act(entry, action) {
    router.post(route('admin.preorders.transition', { preorder: entry.id, action }), {}, { preserveScroll: true });
}

async function cancel(entry) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('preorders.cancel'),
        message: entry.order_no,
        confirmText: t('preorders.cancel'),
    });

    if (result.confirmed) {
        act(entry, 'cancel');
    }
}
</script>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>

<template>
    <div>
        <header class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg font-semibold text-foreground">{{ t('preorders.title') }}</h1>
                <p class="mt-0.5 text-[13px] text-muted">{{ t('preorders.subtitle') }}</p>
            </div>
            <div class="inline-flex h-8 items-center rounded-md border border-border bg-surface p-0.5" role="tablist">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === 'book'"
                    class="inline-flex h-7 items-center gap-1.5 rounded px-3 text-[13px] font-medium transition-colors"
                    :class="tab === 'book' ? 'bg-primary text-primary-foreground' : 'text-muted hover:text-foreground'"
                    @click="tab = 'book'"
                >
                    <AppIcon name="book-open" size="12" />
                    {{ t('preorders.tab_book') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === 'list'"
                    class="inline-flex h-7 items-center gap-1.5 rounded px-3 text-[13px] font-medium transition-colors"
                    :class="tab === 'list' ? 'bg-primary text-primary-foreground' : 'text-muted hover:text-foreground'"
                    @click="tab = 'list'"
                >
                    <AppIcon name="clipboard-list" size="12" />
                    {{ t('preorders.tab_list') }}
                    <span v-if="pendingCount > 0" class="rounded-full bg-warning/20 px-1.5 text-[11px] tabular-nums" :class="tab === 'list' ? 'text-primary-foreground' : 'text-warning'">{{ pendingCount }}</span>
                </button>
            </div>
        </header>

        <MenuBook
            v-show="tab === 'book'"
            embedded
            :branch="branch"
            :categories="categories"
            :tables="tables"
            allow-advance
            :share-url="shareUrl"
            :post-url="route('admin.preorders.store')"
            :placed="placed"
            :storage-key="`dt-preorder-staff:${branch.id}`"
        />

        <section v-show="tab === 'list'">
            <p v-if="preorders.length === 0" class="rounded-lg border border-dashed border-border p-8 text-center text-[13px] text-muted">{{ t('preorders.list_empty') }}</p>

            <ul v-else class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                <li v-for="entry in preorders" :key="entry.id" class="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-mono text-[13px] font-semibold tabular-nums text-foreground">{{ entry.order_no }}</p>
                            <p class="truncate text-[13px] text-foreground">{{ entry.customer_name || t('preorders.walk_in') }}<span v-if="entry.customer_mobile" class="text-muted"> - {{ entry.customer_mobile }}</span></p>
                        </div>
                        <StatusBadge :status="entry.status" />
                    </div>

                    <p class="text-[13px] leading-snug text-foreground">{{ summary(entry) }}</p>

                    <p v-if="entry.address" class="flex items-start gap-1.5 text-xs text-muted"><AppIcon name="location-dot" size="11" class="mt-0.5 shrink-0" />{{ entry.address }}</p>
                    <p v-if="entry.note" class="text-xs text-muted">{{ entry.note }}</p>

                    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                        <div class="flex gap-1"><dt>{{ t('preorders.visit_time') }}:</dt><dd class="text-foreground">{{ entry.expected_at ? formatDateTime(entry.expected_at) : t('preorders.any_time') }}</dd></div>
                        <div v-if="entry.table" class="flex gap-1"><dt>{{ t('preorders.table_label') }}:</dt><dd class="text-foreground">{{ entry.table }}</dd></div>
                        <div v-if="entry.party_size" class="flex gap-1"><dd class="text-foreground">{{ t('preorders.guests', { count: entry.party_size }) }}</dd></div>
                        <div class="flex gap-1"><dd>{{ entry.source === 'public' ? t('preorders.source_public') : t('preorders.source_staff') }}</dd></div>
                        <div v-if="entry.sms_sent" class="flex items-center gap-1"><AppIcon name="mobile-screen" size="11" /><dd>{{ t('preorders.sms_was_sent') }}</dd></div>
                    </dl>

                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border pt-2">
                        <span class="leading-tight">
                            <span class="block text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(entry.total, { decimals: 0 }) }}</span>
                            <span v-if="Number(entry.advance_amount) > 0" class="block text-[11px] text-muted">{{ t('preorders.advance_received') }} {{ formatMoney(entry.advance_amount, { decimals: 0 }) }} - {{ t('preorders.balance_due') }} {{ formatMoney(entry.balance_due, { decimals: 0 }) }}</span>
                        </span>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a :href="entry.print_url" target="_blank" rel="noopener" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background">
                                <AppIcon name="print" size="12" /> {{ t('preorders.print') }}
                            </a>
                            <template v-if="canManage && entry.status === 'pending'">
                                <button type="button" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] text-primary-foreground" @click="act(entry, 'complete')">{{ t('preorders.mark_served') }}</button>
                                <button type="button" class="inline-flex h-7 items-center rounded-md border border-border bg-surface px-2.5 text-[13px] text-danger hover:bg-danger/10" @click="cancel(entry)">{{ t('preorders.cancel') }}</button>
                            </template>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
