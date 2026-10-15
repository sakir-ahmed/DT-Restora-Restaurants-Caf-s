<script setup>
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    discount_limit_percent: props.settings.discount_limit_percent,
    wastage_spike_multiplier: props.settings.wastage_spike_multiplier,
    pos_pin_minutes: props.settings.pos_pin_minutes,
    delivery_otp_enabled: Boolean(props.settings.delivery_otp_enabled),
});

function save() {
    form.put(route('admin.settings.update'), {
        onSuccess: () => notify.success(t('settings.saved')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('settings.title')" :description="t('settings.subtitle')" />

        <form novalidate class="max-w-2xl rounded-lg border border-border bg-surface p-4" @submit.prevent="save">
            <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                <FormField :label="t('settings.discount_limit')" :helper="t('settings.discount_limit_helper')" :error="form.errors.discount_limit_percent" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.discount_limit_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.discount_limit_percent ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

                <FormField :label="t('settings.wastage_spike')" :helper="t('settings.wastage_spike_helper')" :error="form.errors.wastage_spike_multiplier" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.wastage_spike_multiplier"
                            type="number"
                            min="1"
                            max="10"
                            step="0.1"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.wastage_spike_multiplier ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

                <FormField :label="t('settings.pos_pin')" :helper="t('settings.pos_pin_helper')" :error="form.errors.pos_pin_minutes" required>
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.pos_pin_minutes"
                            type="number"
                            min="1"
                            max="60"
                            class="h-7 w-full rounded-md border bg-surface px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                            :class="form.errors.pos_pin_minutes ? 'border-danger' : 'border-border focus:border-accent'"
                        >
                    </template>
                </FormField>

            </div>

            <div class="mt-3 border-t border-border pt-3">
                <SwitchField v-model="form.delivery_otp_enabled" :label="t('settings.delivery_otp')" />
            </div>

            <div class="mt-3 flex justify-end">
                <button
                    type="submit"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
