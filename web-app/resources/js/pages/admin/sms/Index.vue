<script setup>
import { ref } from 'vue';
import SmsHistory from '@/components/admin/SmsHistory.vue';
import SmsPromotionForm from '@/components/admin/SmsPromotionForm.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';

defineProps({
    features: { type: Array, default: () => [] },
    promotionEnabled: { type: Boolean, default: true },
    audiences: { type: Object, default: () => ({}) },
    history: { type: Object, default: null },
});

const tab = ref('history');
</script>

<template>
    <div>
        <PageHeader :title="t('sms.title')" :description="t('sms.subtitle')" />

        <div class="mb-3 flex flex-wrap gap-1" role="tablist">
            <button
                v-for="entry in [{ key: 'history', label: t('sms.tab_history') }, { key: 'promotion', label: t('sms.tab_promotion') }]"
                :key="entry.key"
                type="button"
                role="tab"
                :aria-selected="tab === entry.key"
                class="h-7 rounded-md border px-3 text-[13px] font-medium"
                :class="tab === entry.key ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="tab = entry.key"
            >
                {{ entry.label }}
            </button>
        </div>

        <SmsHistory v-if="tab === 'history'" :history="history" :features="features" />
        <SmsPromotionForm v-if="tab === 'promotion'" :audiences="audiences" :enabled="promotionEnabled" />
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
