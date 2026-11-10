<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
import Reveal from './Reveal.vue';
import { t } from '@/lib/i18n';

defineProps({
    href: { type: String, default: '/menu' },
});
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-12">
        <Reveal>
            <div class="rounded-xl bg-primary px-6 py-10 text-center text-primary-foreground sm:px-12">
                <h2 class="mx-auto max-w-2xl text-2xl font-bold leading-tight sm:text-3xl">{{ t('site.cta_title') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-base opacity-80">{{ t('site.cta_text') }}</p>

                <Link :href="href" class="mt-6 inline-flex h-11 items-center gap-2 rounded-lg bg-accent px-6 text-base font-semibold text-accent-foreground hover:opacity-90">
                    <AppIcon name="bag-shopping" size="15" />
                    {{ t('site.nav_order') }}
                </Link>
            </div>
        </Reveal>
    </section>
</template>
