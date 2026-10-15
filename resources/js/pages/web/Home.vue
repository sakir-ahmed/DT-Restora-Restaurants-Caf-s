<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    branches: { type: Array, required: true },
    featured: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="t('web.seo_home_title')" />

    <div>
        <!-- Hero -->
        <section class="rounded-2xl bg-gradient-to-br from-primary to-primary/70 p-8 text-primary-foreground">
            <h1 class="max-w-lg text-3xl font-bold leading-tight">{{ t('web.hero_title') }}</h1>
            <p class="mt-2 max-w-md text-sm opacity-90">{{ t('web.hero_subtitle') }}</p>

            <div class="mt-5 flex flex-wrap gap-2">
                <Link href="/menu" class="inline-flex h-11 items-center gap-2 rounded-xl bg-accent px-5 text-base font-semibold text-accent-foreground">
                    <AppIcon name="cart-shopping" size="16" />
                    {{ t('web.order_now') }}
                </Link>
                <Link href="/customer/login" class="inline-flex h-11 items-center rounded-xl border border-primary-foreground/30 px-5 text-base font-medium">
                    {{ t('web.nav_login') }}
                </Link>
            </div>
        </section>

        <!-- Branches -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold">{{ t('web.our_branches') }}</h2>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <Link
                    v-for="branch in branches"
                    :key="branch.id"
                    :href="`/menu/${branch.code.toLowerCase()}`"
                    class="rounded-xl border border-border bg-surface p-4 transition-shadow hover:shadow-md"
                >
                    <p class="font-semibold">{{ page.props.locale === 'bn' && branch.name_bn ? branch.name_bn : branch.name }}</p>
                    <p class="mt-1 flex items-center gap-1 text-sm text-muted">
                        <AppIcon name="location-dot" size="12" />
                        {{ branch.address }}, {{ branch.city }}
                    </p>
                    <p v-if="branch.zones?.length" class="mt-1 text-xs text-muted">
                        {{ t('web.delivery_zones') }}: {{ branch.zones.map((z) => z.name).join(', ') }}
                    </p>
                </Link>
            </div>
        </section>

        <!-- Featured items -->
        <section v-if="featured.length" class="mt-8">
            <h2 class="text-lg font-semibold">{{ t('web.popular_items') }}</h2>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link
                    v-for="item in featured"
                    :key="item.id"
                    href="/menu"
                    class="rounded-xl border border-border bg-surface p-3 transition-shadow hover:shadow-md"
                >
                    <p class="line-clamp-2 text-sm font-medium">{{ item.name }}</p>
                    <p class="mt-1 text-sm font-semibold tabular-nums">{{ formatMoney(item.base_price) }}</p>
                </Link>
            </div>
        </section>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
