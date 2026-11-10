<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * Choose the SMS provider and enter its credentials. The fields shown depend
 * on the provider, so adding a provider on the server adds its form here.
 */
const props = defineProps({
    sms: { type: Object, required: true },
});

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

const form = useForm({
    provider: props.sms.provider,
    fields: {},
});

const testForm = useForm({ mobile: '' });

const provider = computed(() => props.sms.providers.find((entry) => entry.key === form.provider) ?? props.sms.providers[0]);
const isSavedProvider = computed(() => form.provider === props.sms.provider);

// Reset the fields to the saved values whenever another provider is picked.
watch(() => form.provider, () => {
    form.clearErrors();
    form.fields = Object.fromEntries((provider.value?.fields ?? []).map((field) => [field.key, field.secret ? '' : field.value]));
}, { immediate: true });

function save() {
    form.put(route('admin.settings.sms'), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success(t('settings.saved'));
            form.fields = Object.fromEntries((provider.value?.fields ?? []).map((field) => [field.key, field.secret ? '' : field.value]));
        },
    });
}

function sendTest() {
    testForm.post(route('admin.settings.sms.test'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash ?? {};

            flash.error ? notify.error(String(flash.error)) : notify.success(String(flash.success ?? t('settings.saved')));
        },
    });
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
        <form novalidate class="space-y-4 rounded-lg border border-border bg-surface p-4" @submit.prevent="save">
            <p v-if="sms.demo" class="rounded-md border border-warning/50 bg-warning/10 px-2.5 py-1.5 text-xs text-foreground">{{ t('settings.sms_demo_note') }}</p>

            <FormField :label="t('settings.sms_provider')" :helper="t('settings.sms_provider_helper')" :error="form.errors.provider">
                <template #default="{ id }">
                    <select :id="id" v-model="form.provider" :class="inputClass">
                        <option v-for="entry in sms.providers" :key="entry.key" :value="entry.key">{{ entry.name }}</option>
                    </select>
                </template>
            </FormField>

            <div class="flex items-center gap-2 text-xs">
                <span
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium"
                    :class="provider.configured ? 'bg-success/10 text-success' : 'bg-warning/15 text-warning'"
                >
                    <AppIcon :name="provider.configured ? 'circle-check' : 'triangle-exclamation'" size="10" />
                    {{ provider.configured ? t('settings.sms_ready') : t('settings.sms_not_ready') }}
                </span>
                <span v-if="isSavedProvider" class="text-muted">{{ t('settings.sms_in_use') }}</span>
                <a :href="provider.docs" target="_blank" rel="noopener noreferrer" class="ml-auto text-accent hover:underline">{{ t('settings.sms_provider_site') }}</a>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <FormField
                    v-for="field in provider.fields"
                    :key="provider.key + field.key"
                    :label="t('settings.sms_field_' + field.key)"
                    :required="field.required"
                    :helper="field.secret && field.saved ? t('settings.sms_secret_saved') : t('settings.sms_field_' + field.key + '_helper')"
                    :error="form.errors['fields.' + field.key]"
                >
                    <template #default="{ id }">
                        <input
                            :id="id"
                            v-model="form.fields[field.key]"
                            :type="field.secret ? 'password' : 'text'"
                            :placeholder="field.secret && field.saved ? '••••••••••••' : ''"
                            autocomplete="off"
                            :class="inputClass"
                        >
                    </template>
                </FormField>
            </div>

            <div class="flex justify-end border-t border-border pt-3">
                <button type="submit" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    <AppIcon v-if="form.processing" name="spinner" spin />
                    {{ t('common.save') }}
                </button>
            </div>
        </form>

        <form novalidate class="space-y-3 self-start rounded-lg border border-border bg-surface p-4" @submit.prevent="sendTest">
            <h2 class="text-[13px] font-semibold text-foreground">{{ t('settings.sms_test_title') }}</h2>
            <p class="text-xs text-muted">{{ t('settings.sms_test_helper') }}</p>
            <FormField :label="t('settings.sms_test_mobile')" :error="testForm.errors.mobile">
                <template #default="{ id }">
                    <input :id="id" v-model="testForm.mobile" type="tel" inputmode="tel" placeholder="017XXXXXXXX" :class="inputClass">
                </template>
            </FormField>
            <button type="submit" :disabled="testForm.processing || ! testForm.mobile || ! sms.providers.find((entry) => entry.key === sms.provider)?.configured" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background disabled:opacity-60">
                <AppIcon v-if="testForm.processing" name="spinner" spin />
                <AppIcon v-else name="paper-plane" size="12" />
                {{ t('settings.sms_test_send') }}
            </button>
            <p class="text-xs text-muted">{{ t('settings.sms_test_saved_only') }}</p>
        </form>
    </div>
</template>
