<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BrandMark from '@/components/ui-kit/BrandMark.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { t } from '@/lib/i18n';

defineProps({
    demoUsers: { type: Array, default: () => [] },
});

const page = usePage();

const highlights = [
    { icon: 'cash-register', label: 'auth.brand_pos' },
    { icon: 'utensils', label: 'auth.brand_kitchen' },
    { icon: 'receipt', label: 'auth.brand_books' },
];

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const demoForm = useForm({
    user_id: null,
});

const loginRef = ref(null);
const passwordRef = ref(null);
const showPassword = ref(false);

onMounted(() => {
    loginRef.value?.focus();
});

function initials(name) {
    return String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function loginAs(userId) {
    demoForm.user_id = userId;
    demoForm.post('/login/demo');
}

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
    <div class="min-h-dvh p-3 sm:p-4 lg:p-5">
        <div class="mx-auto grid min-h-[calc(100dvh-1.5rem)] overflow-hidden rounded-xl border border-border lg:min-h-[calc(100dvh-2.5rem)] lg:grid-cols-[minmax(280px,0.86fr)_minmax(0,1.14fr)]">
            <section class="relative flex flex-col justify-between overflow-hidden bg-primary px-6 py-8 text-primary-foreground sm:px-8 lg:px-10 lg:py-10">
                <div class="pointer-events-none absolute -top-24 -right-16 h-64 w-64 rounded-full bg-accent/30" aria-hidden="true" />
                <div class="pointer-events-none absolute bottom-24 -left-12 h-40 w-40 rounded-full border border-primary-foreground/20" aria-hidden="true" />
                <div class="pointer-events-none absolute right-10 bottom-8 h-16 w-16 rounded-full bg-primary-foreground/10" aria-hidden="true" />

                <div class="relative flex items-center gap-2">
                    <BrandMark :size="32" />
                    <span class="text-sm font-semibold">{{ page.props.appName }}</span>
                </div>

                <div class="relative mt-10 max-w-sm lg:mt-0">
                    <p class="text-2xl font-semibold tracking-tight">{{ t('auth.brand_line') }}</p>
                </div>

                <ul class="relative mt-8 space-y-2">
                    <li v-for="item in highlights" :key="item.label" class="flex items-center gap-2 text-[13px]">
                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-primary-foreground/10">
                            <AppIcon :name="item.icon" size="14" />
                        </span>
                        <span>{{ t(item.label) }}</span>
                    </li>
                </ul>

                <p class="relative mt-8 text-xs text-primary-foreground/70">A Datatech product by Datatech BD Ltd.</p>
            </section>

            <section class="flex flex-col bg-surface px-5 py-16 sm:px-8 lg:py-10">
                <div class="mx-auto w-full max-w-sm">
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
                                    class="h-7 w-full rounded-md border bg-background px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                                    :class="form.errors.login ? 'border-danger' : 'border-border focus:border-accent'"
                                >
                            </template>
                        </FormField>

                        <FormField :label="t('auth.password')" required :error="form.errors.password">
                            <template #default="{ id, ariaInvalid, ariaDescribedby }">
                                <div class="relative">
                                    <input
                                        :id="id"
                                        ref="passwordRef"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        name="password"
                                        autocomplete="current-password"
                                        :aria-invalid="ariaInvalid"
                                        :aria-describedby="ariaDescribedby"
                                        class="h-7 w-full rounded-md border bg-background px-2 pr-8 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40"
                                        :class="form.errors.password ? 'border-danger' : 'border-border focus:border-accent'"
                                    >
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex w-7 cursor-pointer items-center justify-center text-muted hover:text-foreground"
                                        :aria-label="showPassword ? t('auth.hide_password') : t('auth.show_password')"
                                        :aria-pressed="showPassword"
                                        @click="showPassword = !showPassword"
                                    >
                                        <AppIcon :name="showPassword ? 'eye-slash' : 'eye'" size="12" />
                                    </button>
                                </div>
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
                            class="inline-flex h-8 w-full cursor-pointer items-center justify-center gap-1.5 rounded-md bg-primary text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
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
                            class="flex h-8 w-full items-center justify-center gap-2 rounded-md border border-border bg-background text-[13px] font-medium text-foreground hover:border-accent"
                        >
                            <AppIcon name="globe" size="14" />
                            {{ t('auth.google_login') }}
                        </a>
                    </form>
                </div>

                <div v-if="demoUsers.length" class="mx-auto mt-6 w-full max-w-xl border-t border-border pt-4">
                    <h2 class="text-sm font-semibold text-foreground">{{ t('auth.demo_users') }}</h2>
                    <p class="mt-0.5 text-[13px] text-muted">{{ t('auth.demo_hint') }}</p>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="user in demoUsers"
                            :key="user.id"
                            type="button"
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-border bg-background px-2.5 py-2 text-left hover:border-accent disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="demoForm.processing"
                            :aria-label="`${t('auth.demo_login')} ${user.name}`"
                            @click="loginAs(user.id)"
                        >
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-accent/15 text-xs font-semibold text-primary">
                                {{ initials(user.name) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-foreground">{{ user.name }}</span>
                                <span class="flex min-w-0 gap-1.5 truncate text-xs text-muted">
                                    <span class="truncate">{{ user.role }}</span>
                                    <span class="text-border" aria-hidden="true">/</span>
                                    <span class="truncate">{{ user.username }}</span>
                                </span>
                            </span>
                            <AppIcon name="chevron-right" size="12" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import AuthLayout from '@/layouts/AuthLayout.vue';

export default { layout: AuthLayout };
</script>
