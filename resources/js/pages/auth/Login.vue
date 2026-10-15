<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { t } from '@/lib/i18n';

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const loginRef = ref(null);
const passwordRef = ref(null);

onMounted(() => {
    loginRef.value?.focus();
});

async function submit() {
    await form.post('/login', {
        onFinish: async () => {
            if (form.errors.login || form.errors.password) {
                await nextTick();
                (form.errors.login ? loginRef.value : passwordRef.value)?.focus();
            }
        },
    });
}
</script>

<template>
    <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h1 class="text-lg font-semibold text-foreground">{{ t('auth.login_title') }}</h1>
        <p class="mt-0.5 text-[13px] text-muted">{{ t('auth.login_hint') }}</p>

        <form class="mt-4 space-y-3" novalidate @submit.prevent="submit">
            <FormField
                :label="t('auth.login_field')"
                required
                :error="form.errors.login"
                :helper="t('auth.login_helper')"
            >
                <template #default="{ id, ariaInvalid, ariaDescribedby }">
                    <input
                        :id="id"
                        ref="loginRef"
                        v-model="form.login"
                        type="text"
                        name="login"
                        autocomplete="username"
                        :aria-invalid="ariaInvalid"
                        :aria-describedby="ariaDescribedby"
                        class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                        :class="form.errors.login ? 'border-danger' : 'border-border focus:border-accent'"
                    >
                </template>
            </FormField>

            <FormField :label="t('auth.password')" required :error="form.errors.password">
                <template #default="{ id, ariaInvalid, ariaDescribedby }">
                    <input
                        :id="id"
                        ref="passwordRef"
                        v-model="form.password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        :aria-invalid="ariaInvalid"
                        :aria-describedby="ariaDescribedby"
                        class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                        :class="form.errors.password ? 'border-danger' : 'border-border focus:border-accent'"
                    >
                </template>
            </FormField>

            <label class="flex items-center gap-2 text-[13px] text-foreground">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border accent-accent"
                >
                {{ t('auth.remember') }}
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-md bg-primary text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <AppIcon v-if="form.processing" name="spinner" spin />
                {{ t('auth.sign_in') }}
            </button>

            <div class="relative py-1 text-center">
                <span class="relative z-10 bg-surface px-2 text-xs text-muted">{{ t('common.or') }}</span>
                <span class="absolute inset-x-0 top-1/2 h-px bg-border" aria-hidden="true" />
            </div>

            <a
                href="/auth/google/redirect"
                class="flex h-8 w-full items-center justify-center gap-2 rounded-md border border-border bg-surface text-[13px] font-medium text-foreground hover:bg-background"
            >
                <AppIcon name="globe" size="14" />
                {{ t('auth.google_login') }}
            </a>
        </form>
    </div>
</template>

<script>
import AuthLayout from '@/layouts/AuthLayout.vue';

export default { layout: AuthLayout };
</script>
