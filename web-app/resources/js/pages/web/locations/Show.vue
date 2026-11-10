<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CtaBanner from '@/components/site/CtaBanner.vue';
import DishCard from '@/components/site/DishCard.vue';
import HoursList from '@/components/site/HoursList.vue';
import MediaViewer from '@/components/site/MediaViewer.vue';
import PageHero from '@/components/site/PageHero.vue';
import Reveal from '@/components/site/Reveal.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { t } from '@/lib/i18n';
import { formatMoney, localDigits } from '@/lib/format';
import { branchAddress, directionsUrl, localized, menuUrl } from '@/lib/site';

const props = defineProps({
    branch: { type: Object, required: true },
    popular: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    seo: { type: Object, default: null },
});

const name = computed(() => localized(props.branch, 'name'));
const crumbs = computed(() => [
    { label: t('site.nav_home'), href: '/' },
    { label: t('site.nav_locations'), href: '/locations' },
    { label: name.value },
]);
</script>

<template>
    <div>
        <PageHero :eyebrow="t('site.locations_eyebrow')" :title="name" :text="branchAddress(branch)" :breadcrumbs="crumbs">
            <div class="mt-7 flex flex-wrap gap-3">
                <Link v-if="branch.online_ordering_enabled" :href="menuUrl(branch.code)" class="inline-flex h-10 items-center gap-2 rounded-lg bg-accent px-6 text-base font-semibold text-accent-foreground ">
                    <AppIcon name="bag-shopping" size="15" />
                    {{ t('site.order_from_branch') }}
                </Link>
                <a :href="directionsUrl(branch)" target="_blank" rel="noopener" class="bg-surface border border-border inline-flex h-10 items-center gap-2 rounded-lg px-6 text-base font-semibold">
                    <AppIcon name="map-pin" size="14" />
                    {{ t('site.directions') }}
                </a>
                <a v-if="branch.phone" :href="`tel:${branch.phone}`" class="bg-surface border border-border inline-flex h-10 items-center gap-2 rounded-lg px-6 text-base font-semibold">
                    <AppIcon name="phone" size="14" />
                    {{ localDigits(branch.phone) }}
                </a>
            </div>
        </PageHero>

        <section class="mx-auto grid max-w-6xl gap-6 px-4 py-10 lg:grid-cols-5">
            <Reveal class="lg:col-span-2">
                <div class="h-full rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <h2 class="text-2xl font-bold">{{ t('site.location_info_title') }}</h2>
                    <dl class="mt-5 space-y-5">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.location_address') }}</dt>
                            <dd class="mt-1">{{ branchAddress(branch) }}</dd>
                        </div>
                        <div v-if="branch.phone">
                            <dt class="text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.location_phone') }}</dt>
                            <dd class="mt-1"><a :href="`tel:${branch.phone}`" class="font-semibold hover:underline">{{ localDigits(branch.phone) }}</a></dd>
                        </div>
                        <div>
                            <dt class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ t('site.location_hours_title') }}</dt>
                            <dd><HoursList :hours="branch.hours" /></dd>
                        </div>
                    </dl>
                    <p v-if="! branch.online_ordering_enabled" class="mt-5 rounded-xl bg-warning/15 px-4 py-3 text-sm">{{ t('site.location_online_off') }}</p>
                </div>
            </Reveal>

            <Reveal :delay="100" class="lg:col-span-3">
                <div class="h-full rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <h2 class="text-2xl font-bold">{{ t('site.location_zones_title') }}</h2>
                    <p class="mt-1 text-muted">{{ t('site.location_zones_text') }}</p>

                    <div v-if="branch.zones.length" class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-left text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs uppercase tracking-widest text-muted">
                                    <th scope="col" class="py-2 pr-3 font-semibold">{{ t('site.zone_area') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right font-semibold">{{ t('site.zone_fee') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right font-semibold">{{ t('site.zone_min') }}</th>
                                    <th scope="col" class="py-2 pl-3 text-right font-semibold">{{ t('site.zone_eta') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="zone in branch.zones" :key="zone.id" class="border-b border-border/60 last:border-0">
                                    <th scope="row" class="py-3 pr-3 font-semibold">{{ zone.name }}</th>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ formatMoney(zone.fee, { decimals: 0 }) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ formatMoney(zone.min_order, { decimals: 0 }) }}</td>
                                    <td class="py-3 pl-3 text-right tabular-nums">{{ t('site.zone_minutes', { minutes: formatMoney(zone.eta_minutes, { decimals: 0 }) }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-5 rounded-xl bg-surface px-4 py-3 text-sm text-muted">{{ t('site.location_zones_empty') }}</p>
                </div>
            </Reveal>
        </section>

        <section v-if="media.length" class="mx-auto max-w-6xl px-4 pb-10">
            <Reveal>
                <SectionHeading :title="t('site.outlet_media_title')" />
                <div class="mt-6 max-w-3xl"><MediaViewer :items="media" :name="name" aspect="aspect-video" /></div>
            </Reveal>
        </section>

        <section v-if="popular.length" class="border-y border-border bg-surface py-12">
            <div class="mx-auto max-w-6xl px-4">
                <Reveal><SectionHeading :title="t('site.location_popular_title')" /></Reveal>
                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Reveal v-for="(item, index) in popular" :key="item.id" as="li" :delay="(index % 3) * 90">
                        <DishCard :item="item" :branch-code="branch.code" />
                    </Reveal>
                </ul>
            </div>
        </section>

        <CtaBanner :href="menuUrl(branch.code)" />
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
