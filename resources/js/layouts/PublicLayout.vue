<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';
import { t } from '@/lib/i18n';
import { cartCount, cartSubtotal } from '@/lib/cart';
import { formatMoney } from '@/lib/format';

const page = usePage();

const customer = computed(() => page.props.auth?.customer ?? null);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="sticky top-0 z-30 border-b border-border bg-surface/95 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-4">
                <Link href="/" class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppIcon name="utensils" size="16" />
                    </span>
                    <span class="font-semibold">{{ page.props.appName }}</span>
                </Link>

                <nav class="ml-auto flex items-center gap-1.5">
                    <Link href="/menu" class="hidden rounded-md px-2 py-1.5 text-sm text-muted hover:text-foreground sm:block">
                        {{ t('web.nav_menu') }}
                    </Link>
                    <Link v-if="customer" href="/account" class="hidden rounded-md px-2 py-1.5 text-sm text-muted hover:text-foreground sm:block">
                        {{ t('web.nav_account') }}
                    </Link>
                    <Link v-else href="/customer/login" class="hidden rounded-md px-2 py-1.5 text-sm text-muted hover:text-foreground sm:block">
                        {{ t('web.nav_login') }}
                    </Link>

                    <LanguageSwitch />
                    <ThemeToggle />

                    <Link
                        href="/checkout"
                        class="relative ml-1 inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3 text-sm font-semibold text-accent-foreground"
                    >
                        <AppIcon name="cart-shopping" size="14" />
                        <span class="tabular-nums">{{ formatMoney(cartSubtotal(), { decimals: 0 }) }}</span>
                        <span v-if="cartCount() > 0" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                            {{ cartCount() }}
                        </span>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6">
            <slot />
        </main>

        <footer class="border-t border-border bg-surface">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-sm text-muted">
                <p>Datatech BD Ltd, Dhaka</p>
                <div class="flex items-center gap-4">
                    <a href="/privacy" class="hover:text-foreground">{{ t('web.privacy') }}</a>
                    <a href="/terms" class="hover:text-foreground">{{ t('web.terms') }}</a>
                </div>
            </div>
        </footer>
    </div>
</template>
