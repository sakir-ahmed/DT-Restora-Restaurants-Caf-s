<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    page: { type: String, required: true },
    sections: { type: Number, required: true },
    seo: { type: Object, default: null },
});

const shared = usePage();
const app = computed(() => shared.props.appName);

const title = computed(() => t(`legal.${props.page}.title`));

const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: title.value },
]);

const parts = computed(() => Array.from({ length: props.sections }, (_, index) => {
    const number = index + 1;

    return {
        number,
        title: t(`legal.${props.page}.s${number}_title`, { app: app.value }),
        paragraphs: t(`legal.${props.page}.s${number}_body`, { app: app.value }).split('\n\n'),
    };
}));

const related = computed(() => [
    { key: 'privacy', href: '/privacy', label: t('legal.privacy.title') },
    { key: 'terms', href: '/terms', label: t('legal.terms.title') },
    { key: 'refund', href: '/refund-policy', label: t('legal.refund.title') },
    { key: 'delivery', href: '/delivery-policy', label: t('legal.delivery.title') },
    { key: 'cookies', href: '/cookie-policy', label: t('legal.cookies.title') },
].filter((entry) => entry.key !== props.page));
</script>

<template>
    <div>
        <PageHero :title="title" :text="t(`legal.${page}.intro`, { app })" :breadcrumbs="crumbs" />

        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[220px_1fr]">
            <nav class="hidden lg:block" :aria-label="title">
                <ul class="sticky top-24 space-y-1 text-sm">
                    <li v-for="part in parts" :key="part.number">
                        <a :href="`#section-${part.number}`" class="block rounded-md px-2 py-1.5 text-muted hover:bg-surface hover:text-foreground">{{ part.title }}</a>
                    </li>
                </ul>
            </nav>

            <article class="min-w-0 space-y-4">
                <Reveal v-for="part in parts" :key="part.number" as="section" class="scroll-mt-24 rounded-xl border border-border bg-surface p-5">
                    <h2 :id="`section-${part.number}`" class="text-lg font-semibold">{{ part.number }}. {{ part.title }}</h2>
                    <div class="mt-2 space-y-3 text-base leading-relaxed text-muted">
                        <p v-for="(paragraph, index) in part.paragraphs" :key="index">{{ paragraph }}</p>
                    </div>
                </Reveal>

                <div class="rounded-xl border border-border bg-background p-5 text-sm">
                    <p class="text-muted">{{ t('legal.contact_line') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link href="/contact" class="inline-flex h-9 items-center rounded-lg bg-accent px-4 font-semibold text-accent-foreground hover:opacity-90">{{ t('site.nav_contact') }}</Link>
                        <Link v-for="entry in related" :key="entry.key" :href="entry.href" class="inline-flex h-9 items-center rounded-lg border border-border bg-surface px-3 font-medium hover:bg-background">{{ entry.label }}</Link>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
