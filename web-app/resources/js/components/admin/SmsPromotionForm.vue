<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { analyzeSms } from '@/lib/smsText';

/**
 * Write a promotion and send it to a group of customers.
 */
const props = defineProps({
    audiences: { type: Object, default: () => ({}) },
    enabled: { type: Boolean, default: true },
});

const confirm = useConfirm();
const MAX_LENGTH = 320;
const form = useForm({ audience: 'all', message: '' });

const audienceCount = computed(() => Number(props.audiences?.[form.audience] ?? 0));
const stats = computed(() => analyzeSms(form.message));
const totalParts = computed(() => stats.value.segments * audienceCount.value);

async function send() {
    const result = await confirm({
        variant: 'warning',
        level: 'simple',
        title: t('sms.promo_confirm_title'),
        message: t('sms.promo_confirm', { count: audienceCount.value, parts: totalParts.value }),
        confirmText: t('sms.promo_send'),
    });

    if (! result.confirmed) {
        return;
    }

    form.post(route('admin.sms.promotion'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash ?? {};

            if (flash.error) {
                notify.error(String(flash.error));

                return;
            }

            notify.success(String(flash.success ?? ''));
            form.reset('message');
        },
    });
}
</script>

<template>
    <form novalidate class="max-w-2xl space-y-3 rounded-lg border border-border bg-surface p-4" @submit.prevent="send">
        <h2 class="text-[13px] font-semibold text-foreground">{{ t('sms.promo_title') }}</h2>
        <p class="text-xs text-muted">{{ t('sms.promo_helper') }}</p>

        <p v-if="! enabled" class="rounded-md border border-warning/50 bg-warning/10 px-2.5 py-1.5 text-xs text-foreground">{{ t('sms.promotion_feature_off') }}</p>

        <FormField :label="t('sms.promo_audience')" :helper="t('sms.promo_count', { count: audienceCount })" :error="form.errors.audience">
            <template #default="{ id }">
                <select :id="id" v-model="form.audience" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent">
                    <option v-for="(count, key) in audiences" :key="key" :value="key">{{ t('sms.audience.' + key) }} ({{ count }})</option>
                </select>
            </template>
        </FormField>

        <FormField :label="t('sms.promo_message')" :error="form.errors.message">
            <template #default="{ id }">
                <textarea :id="id" v-model="form.message" rows="5" :maxlength="MAX_LENGTH" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
            </template>
        </FormField>

        <p class="text-xs tabular-nums text-muted">
            {{ t('sms.count_line', { chars: stats.chars, max: MAX_LENGTH, words: stats.words, parts: stats.segments }) }}
            <span v-if="stats.encoding === 'unicode' && stats.chars > 0"> {{ t('sms.unicode_note', { size: stats.perSegment }) }}</span>
        </p>

        <button type="submit" :disabled="form.processing || ! form.message.trim() || audienceCount === 0 || ! enabled" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
            <AppIcon v-if="form.processing" name="spinner" spin />
            <AppIcon v-else name="paper-plane" size="12" />
            {{ t('sms.promo_send') }}
        </button>
    </form>
</template>
