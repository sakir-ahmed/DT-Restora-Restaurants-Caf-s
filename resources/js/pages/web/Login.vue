<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import { t } from '@/lib/i18n';

defineProps({
    mobile: { type: String, default: '' },
    codeSent: { type: Boolean, default: false },
});

const mobile = ref('');
const code = ref('');
const sent = ref(false);
const busy = ref(false);
const message = ref('');

function send() {
    busy.value = true;
    message.value = '';

    router.post(route('web.customer.otp.send'), { mobile: mobile.value }, {
        preserveState: true,
        onSuccess: () => {
            sent.value = true;
        },
        onError: (errors) => {
            message.value = Object.values(errors)[0] ?? t('common.error_occurred');
        },
        onFinish: () => (busy.value = false),
    });
}

function verify() {
    busy.value = true;
    message.value = '';

    router.post(route('web.customer.otp.verify'), { mobile: mobile.value, code: code.value }, {
        onError: (errors) => {
            message.value = Object.values(errors)[0] ?? t('common.error_occurred');
            busy.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('web.nav_login')" />

    <div class="mx-auto max-w-sm rounded-xl border border-border bg-surface p-6">
        <h1 class="text-lg font-semibold">{{ t('web.customer_login') }}</h1>
        <p class="mt-0.5 text-sm text-muted">{{ t('web.customer_login_hint') }}</p>

        <div class="mt-4 space-y-3">
            <FormField :label="t('staff.mobile')" required>
                <div class="flex gap-1.5">
                    <MobileInput v-model="mobile" />
                    <button type="button" :disabled="busy || ! mobile" class="h-9 shrink-0 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground disabled:opacity-50" @click="send">
                        {{ sent ? t('web.resend_otp') : t('web.send_otp') }}
                    </button>
                </div>
            </FormField>

            <FormField v-if="sent" :label="t('web.otp_code')" required :helper="t('web.otp_helper')">
                <template #default="{ id }">
                    <input :id="id" v-model="code" type="text" inputmode="numeric" maxlength="6" class="h-10 w-full rounded-md border border-border bg-surface px-2 text-center text-lg tracking-[0.4em] tabular-nums">
                </template>
            </FormField>

            <p v-if="message" role="alert" class="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">{{ message }}</p>

            <a :href="route('web.customer.google.redirect')" class="flex h-10 w-full items-center justify-center rounded-lg border border-border text-sm font-medium">
                {{ t('web.continue_google') }}
            </a>

            <button
                v-if="sent"
                type="button"
                :disabled="busy || code.length !== 6"
                class="flex h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-accent text-sm font-semibold text-accent-foreground disabled:opacity-50"
                @click="verify"
            >
                <AppIcon v-if="busy" name="spinner" spin />
                {{ t('auth.sign_in') }}
            </button>
        </div>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
