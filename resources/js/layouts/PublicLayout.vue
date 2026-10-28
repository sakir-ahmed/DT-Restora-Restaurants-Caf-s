<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BrandMark from '@/components/ui-kit/BrandMark.vue';
import BrandName from '@/components/ui-kit/BrandName.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';
import { track } from '@/lib/demoTracker';
import { t } from '@/lib/i18n';
import { cartCount, cartSubtotal } from '@/lib/cart';
import { formatMoney, localDigits } from '@/lib/format';
import { branchAddress, locationUrl } from '@/lib/site';
import SocialIcon from '@/components/site/SocialIcon.vue';
import { currentLocale } from '@/lib/i18n';

const page = usePage();

const mounted = ref(false);
const wordmark = '/images/brand/wordmark-gold.png';
const scrolled = ref(false);
const drawerOpen = ref(false);

const customer = computed(() => page.props.auth?.customer ?? null);
const staff = computed(() => page.props.auth?.user ?? null);
const branches = computed(() => page.props.site?.branches ?? []);

// A network without a saved link still shows its icon, pointing at "#".
const socialLinks = computed(() => ['facebook', 'instagram', 'youtube', 'tiktok', 'whatsapp'].map((key) => {
    const url = page.props.site?.social?.[`social_${key}`];

    return { key, label: t(`site.social_${key}`), href: url || '#', live: Boolean(url) };
}));
// Demo sites expose the back office login, which is otherwise hidden from the public site.
const showAdminDemo = computed(() => page.props.isDemo === true && ! staff.value);

const accountLink = computed(() => {
    if (staff.value) {
        return { href: '/admin', label: t('nav.dashboard'), icon: 'chart-line' };
    }

    if (customer.value) {
        return { href: '/account', label: t('web.nav_account'), icon: 'circle-user' };
    }

    return { href: '/customer/login', label: t('web.nav_login'), icon: null };
});

/**
 * Marketing pages render their own full-width sections; the transactional
 * pages (checkout, account, login, tracking) keep the narrow container.
 */
const fullBleed = computed(() => [
    'web/Home', 'web/About', 'web/Gallery', 'web/Contact', 'web/Menu', 'web/Item',
    'web/Legal', 'web/Faq', 'web/blog/Index', 'web/blog/Show', 'web/careers/Index', 'web/careers/Show', 'web/locations/Index', 'web/locations/Show',
].includes(page.component));

const links = computed(() => [
    { href: '/', label: t('site.nav_home'), match: (url) => url === '/' },
    { href: '/menu', label: t('site.nav_menu'), match: (url) => url.startsWith('/menu') },
    { href: '/gallery', label: t('site.nav_gallery'), match: (url) => url.startsWith('/gallery') },
    { href: '/blog', label: t('site.nav_blog'), match: (url) => url.startsWith('/blog') },
    { href: '/about', label: t('site.nav_about'), match: (url) => url.startsWith('/about') },
    { href: '/locations', label: t('site.nav_locations'), match: (url) => url.startsWith('/locations') },
    { href: '/contact', label: t('site.nav_contact'), match: (url) => url.startsWith('/contact') },
]);

const exploreLinks = computed(() => [
    ...links.value,
    { href: '/careers', label: t('site.nav_careers') },
    { href: '/faq', label: t('site.nav_faq') },
]);

const legalLinks = computed(() => [
    { href: '/privacy', label: t('legal.privacy.title') },
    { href: '/terms', label: t('legal.terms.title') },
    { href: '/delivery-policy', label: t('legal.delivery.title') },
    { href: '/refund-policy', label: t('legal.refund.title') },
    { href: '/cookie-policy', label: t('legal.cookies.title') },
]);

const path = computed(() => (page.url ?? '/').split('?')[0]);

function branchName(branch) {
    return currentLocale() === 'bn' && branch.name_bn ? branch.name_bn : branch.name;
}

function onScroll() {
    scrolled.value = window.scrollY > 8;
}

watch(drawerOpen, (open) => {
    document.documentElement.classList.toggle('overflow-hidden', open);
});

let removeNavigateListener = null;

onMounted(() => {
    mounted.value = true;
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    removeNavigateListener = router.on('navigate', () => {
        drawerOpen.value = false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    removeNavigateListener?.();
    document.documentElement.classList.remove('overflow-hidden');
});
</script>

<template>
    <div class="site flex min-h-screen flex-col bg-background text-foreground">
        <SeoHead />

        <a href="#main" class="sr-only z-[60] rounded-lg bg-accent px-4 py-2 font-semibold text-accent-foreground focus:not-sr-only focus:fixed focus:left-4 focus:top-[calc(1rem+var(--demo-banner-h))]">
            {{ t('site.skip_to_content') }}
        </a>

        <header
            class="bg-surface border border-border sticky top-(--demo-banner-h) z-40 border-x-0 border-t-0 transition-shadow duration-300"
            :class="scrolled ? 'shadow-sm' : ''"
        >
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-3 sm:gap-3 sm:px-4">
                <Link href="/" class="group flex min-w-0 items-center gap-2 sm:gap-2.5" :aria-label="page.props.appName">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center duration-300 group-hover:rotate-[-8deg] sm:h-10 sm:w-10">
                        <BrandMark :size="40" class="h-full w-full" />
                    </span>
                    <BrandName class="truncate text-[15px] font-bold sm:text-lg" :name="page.props.appName" />
                </Link>

                <nav class="ml-6 hidden items-center lg:flex" aria-label="Main">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="nav-link rounded-md px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-foreground aria-[current=page]:text-foreground"
                        :aria-current="link.match(path) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                    <Link
                        v-if="showAdminDemo"
                        href="/login"
                        class="demo-admin-cta hidden h-9 items-center gap-1.5 rounded-lg border border-accent/60 bg-accent/10 px-3 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-accent-foreground sm:inline-flex"
                        @click="track('cta_click', 'admin_header')"
                    >
                        <AppIcon name="user-shield" size="14" />
                        {{ t('demo.admin_cta') }}
                    </Link>

                    <Link :href="accountLink.href" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-muted hover:text-foreground md:inline-flex">
                        <AppIcon v-if="accountLink.icon" :name="accountLink.icon" size="14" />
                        {{ accountLink.label }}
                    </Link>

                    <LanguageSwitch />
                    <div class="hidden sm:block"><ThemeToggle /></div>

                    <Link
                        href="/checkout"
                        class="relative inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3 text-sm font-semibold text-accent-foreground hover:opacity-90 "
                        :aria-label="t('web.view_cart')"
                    >
                        <AppIcon name="cart-shopping" size="14" />
                        <span class="hidden tabular-nums sm:inline">{{ mounted ? formatMoney(cartSubtotal(), { decimals: 0 }) : formatMoney(0, { decimals: 0 }) }}</span>
                        <span v-if="mounted && cartCount() > 0" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
                            {{ cartCount() }}
                        </span>
                    </Link>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-surface lg:hidden"
                        :aria-label="drawerOpen ? t('site.nav_close_menu') : t('site.nav_open_menu')"
                        :aria-expanded="drawerOpen"
                        aria-controls="mobile-drawer"
                        @click="drawerOpen = ! drawerOpen"
                    >
                        <AppIcon :name="drawerOpen ? 'xmark' : 'bars'" size="16" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Mobile drawer -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-y-4 opacity-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="-translate-y-4 opacity-0"
        >
            <div v-if="drawerOpen" id="mobile-drawer" class="fixed inset-x-0 bottom-0 top-[calc(4rem+var(--demo-banner-h))] z-30 overflow-y-auto bg-background/95 px-4 pb-10 pt-6 backdrop-blur-xl lg:hidden">
                <nav class="mx-auto max-w-md space-y-1" aria-label="Mobile">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="flex items-center justify-between rounded-xl px-4 py-3 text-lg font-semibold transition-colors"
                        :class="link.match(path) ? 'bg-accent/15 text-foreground' : 'text-foreground/80 hover:bg-surface'"
                    >
                        {{ link.label }}
                        <AppIcon name="arrow-right" size="14" class="text-muted" />
                    </Link>
                </nav>

                <div class="mx-auto mt-6 flex max-w-md flex-col gap-3">
                    <Link href="/menu" class="flex h-11 items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-foreground ">
                        <AppIcon name="bag-shopping" size="16" />
                        {{ t('site.nav_order') }}
                    </Link>
                    <Link
                        v-if="showAdminDemo"
                        href="/login"
                        class="flex h-10 items-center justify-center gap-2 rounded-lg border border-accent/60 bg-accent/10 text-sm font-semibold"
                        @click="track('cta_click', 'admin_drawer')"
                    >
                        <AppIcon name="user-shield" size="14" />
                        {{ t('demo.admin_cta') }}
                    </Link>
                    <Link :href="accountLink.href" class="flex h-10 items-center justify-center gap-2 rounded-lg border border-border bg-surface text-sm font-medium">
                        <AppIcon v-if="accountLink.icon" :name="accountLink.icon" size="14" />
                        {{ accountLink.label }}
                    </Link>
                    <div class="flex justify-center sm:hidden"><ThemeToggle /></div>
                </div>
            </div>
        </Transition>

        <main id="main" :key="page.url" class="animate-page-in flex-1" :class="fullBleed ? '' : 'mx-auto w-full max-w-5xl px-4 py-6'">
            <slot />
        </main>

        <footer class="bg-[#17120b] text-[#cdbfa6]">
            <div class="h-px bg-gradient-to-r from-transparent via-[#c9a24a] to-transparent" aria-hidden="true" />

            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-12">
                <div class="sm:col-span-2 lg:col-span-4">
                    <Link href="/" class="inline-block" :aria-label="page.props.appName">
                        <img :src="wordmark" :alt="page.props.appName" width="962" height="353" loading="lazy" decoding="async" class="h-16 w-auto">
                    </Link>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed">{{ t('site.footer_text') }}</p>

                    <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-[#f6e7bd]">{{ t('site.footer_follow') }}</p>
                    <ul class="mt-3 flex flex-wrap gap-2.5">
                        <li v-for="network in socialLinks" :key="network.key">
                            <a
                                :href="network.href"
                                :aria-label="network.label"
                                :title="network.label"
                                :target="network.live ? '_blank' : undefined"
                                :rel="network.live ? 'noopener noreferrer' : undefined"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/15 text-[#e6d8bd] transition duration-200 hover:-translate-y-0.5 hover:border-[#c9a24a] hover:bg-[#c9a24a] hover:text-[#17120b]"
                            >
                                <SocialIcon :network="network.key" :size="18" />
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-[#f6e7bd]">{{ t('site.footer_explore') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-for="link in exploreLinks" :key="link.href">
                            <Link :href="link.href" class="transition-colors hover:text-[#f6e7bd]">{{ link.label }}</Link>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-[#f6e7bd]">{{ t('site.footer_branches') }}</h2>
                    <ul class="mt-4 space-y-5 text-sm">
                        <li v-for="branch in branches" :key="branch.code">
                            <Link :href="locationUrl(branch.slug)" class="font-semibold text-[#f6efe0] transition-colors hover:text-[#f6e7bd]">{{ branchName(branch) }}</Link>
                            <p v-if="branchAddress(branch)" class="mt-2 flex gap-2.5 leading-relaxed">
                                <AppIcon name="location-dot" size="13" class="mt-1 shrink-0 text-[#c9a24a]" />
                                <span>{{ branchAddress(branch) }}</span>
                            </p>
                            <p v-if="branch.phone" class="mt-2 flex items-center gap-2.5">
                                <AppIcon name="phone" size="13" class="shrink-0 text-[#c9a24a]" />
                                <a :href="`tel:${branch.phone}`" class="transition-colors hover:text-[#f6e7bd]">{{ localDigits(branch.phone) }}</a>
                            </p>
                        </li>
                    </ul>
                </div>

                <div class="lg:col-span-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-[#f6e7bd]">{{ t('site.footer_legal') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li v-for="link in legalLinks" :key="link.href">
                            <Link :href="link.href" class="transition-colors hover:text-[#f6e7bd]">{{ link.label }}</Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm">
                    <p class="text-[#a89b82]">&copy; {{ localDigits(new Date().getFullYear()) }} {{ page.props.appName }}. {{ t('site.footer_rights') }} A Datatech product by Datatech BD Ltd.</p>
                    <a href="#main" class="inline-flex items-center gap-2 rounded-lg border border-white/15 px-3 py-1.5 transition-colors hover:border-[#c9a24a] hover:text-[#f6e7bd]">
                        <AppIcon name="arrow-up" size="11" />
                        {{ t('site.back_to_top') }}
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>
