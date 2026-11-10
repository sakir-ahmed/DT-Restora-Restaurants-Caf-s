<script setup>
import { router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

/**
 * Every SMS the system tried to send, newest first. The list is loaded when
 * this tab opens, not with the rest of Settings.
 */
const props = defineProps({
    history: { type: Object, default: null },
    features: { type: Array, required: true },
});

const feature = ref('');
const status = ref('');
const q = ref('');
const page = ref(1);
const loading = ref(false);

const featureKeys = [...props.features.map((entry) => entry.key), 'test', 'general'];
const statuses = ['sent', 'failed', 'skipped', 'demo'];

function load() {
    loading.value = true;

    router.reload({
        only: ['history'],
        data: {
            feature: feature.value || undefined,
            status: status.value || undefined,
            q: q.value || undefined,
            page: page.value > 1 ? page.value : undefined,
        },
        preserveScroll: true,
        preserveUrl: true,
        onFinish: () => { loading.value = false; },
    });
}

let timer = null;

watch([feature, status], () => { page.value = 1; load(); });
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => { page.value = 1; load(); }, 350);
});

onMounted(load);

function go(delta) {
    page.value += delta;
    load();
}

function featureLabel(key) {
    if (key === 'test') {
        return t('sms.feature_test');
    }

    return key === 'general' ? t('sms.feature_general') : t('sms.feature.' + key + '.name');
}

function statusClass(value) {
    return {
        sent: 'bg-success/10 text-success',
        failed: 'bg-danger/10 text-danger',
        skipped: 'bg-muted/10 text-muted',
        demo: 'bg-warning/15 text-warning',
    }[value] ?? 'bg-muted/10 text-muted';
}

function when(value) {
    return value ? new Date(value).toLocaleString() : '';
}

const selectClass = 'h-7 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent';
</script>

<template>
    <div class="max-w-6xl rounded-lg border border-border bg-surface p-4">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <select v-model="feature" :class="selectClass" :aria-label="t('sms.history_feature')">
                <option value="">{{ t('sms.history_all_features') }}</option>
                <option v-for="key in featureKeys" :key="key" :value="key">{{ featureLabel(key) }}</option>
            </select>
            <select v-model="status" :class="selectClass" :aria-label="t('common.status')">
                <option value="">{{ t('sms.history_all_statuses') }}</option>
                <option v-for="value in statuses" :key="value" :value="value">{{ t('sms.status.' + value) }}</option>
            </select>
            <input v-model="q" type="search" inputmode="tel" :placeholder="t('sms.history_search')" :class="[selectClass, 'w-44']">
            <span class="ml-auto text-xs text-muted">{{ history ? t('sms.history_total', { count: history.total }) : '' }}</span>
        </div>

        <div class="overflow-x-auto rounded-md border border-border" :class="loading ? 'opacity-60' : ''">
            <table class="w-full text-[13px]">
                <thead class="bg-background text-left text-xs uppercase text-muted">
                    <tr>
                        <th class="px-2 py-1.5">{{ t('sms.history_when') }}</th>
                        <th class="px-2 py-1.5">{{ t('sms.history_feature') }}</th>
                        <th class="px-2 py-1.5">{{ t('sms.history_mobile') }}</th>
                        <th class="px-2 py-1.5">{{ t('sms.history_message') }}</th>
                        <th class="px-2 py-1.5">{{ t('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in history?.data ?? []" :key="row.id" class="border-t border-border align-top">
                        <td class="whitespace-nowrap px-2 py-1.5 tabular-nums">{{ when(row.at) }}</td>
                        <td class="px-2 py-1.5">{{ featureLabel(row.feature) }}</td>
                        <td class="whitespace-nowrap px-2 py-1.5 tabular-nums">{{ row.mobile }}</td>
                        <td class="max-w-md px-2 py-1.5">
                            <p class="break-words">{{ row.message }}</p>
                            <p v-if="row.response" class="mt-0.5 text-xs text-muted">{{ row.response }}</p>
                        </td>
                        <td class="px-2 py-1.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(row.status)">{{ t('sms.status.' + row.status) }}</span>
                            <p v-if="row.by" class="mt-0.5 text-xs text-muted">{{ row.by }}</p>
                        </td>
                    </tr>
                    <tr v-if="history && history.data.length === 0">
                        <td colspan="5" class="px-2 py-6 text-center text-muted">{{ t('sms.history_empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="history && history.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-xs">
            <button type="button" :disabled="history.current_page <= 1" class="inline-flex h-7 items-center gap-1 rounded-md border border-border px-2 hover:bg-background disabled:opacity-50" @click="go(-1)">
                <AppIcon name="chevron-left" size="10" />{{ t('common.previous_page') }}
            </button>
            <span class="tabular-nums">{{ history.current_page }} / {{ history.last_page }}</span>
            <button type="button" :disabled="history.current_page >= history.last_page" class="inline-flex h-7 items-center gap-1 rounded-md border border-border px-2 hover:bg-background disabled:opacity-50" @click="go(1)">
                {{ t('common.next_page') }}<AppIcon name="chevron-right" size="10" />
            </button>
        </div>
    </div>
</template>
