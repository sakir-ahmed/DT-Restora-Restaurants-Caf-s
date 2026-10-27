<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import BrandMark from '@/components/ui-kit/BrandMark.vue';
import BrandName from '@/components/ui-kit/BrandName.vue';
import CommandPalette from '@/components/ui-kit/CommandPalette.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import NotificationBell from '@/components/ui-kit/NotificationBell.vue';
import SyncStatusPill from '@/components/ui-kit/SyncStatusPill.vue';
import ThemeToggle from '@/components/ui-kit/ThemeToggle.vue';
import { t } from '@/lib/i18n';
import { configureSounds, preloadSounds } from '@/lib/sounds';

const page = usePage();

const user = computed(() => page.props.auth?.user);
const permissions = computed(() => new Set(user.value?.permissions ?? []));

const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

// The branch's sound settings ride along with every page; keep the local copy fresh.
watch(() => page.props.sounds, (sounds) => configureSounds(sounds), { immediate: true });

onMounted(() => {
    preloadSounds();

    const saved = localStorage.getItem('sidebar-collapsed');
    sidebarCollapsed.value = saved === '1';
});

const sidebarCollapsed = ref(false);

function toggleSidebar() {
    if (window.innerWidth < 1024) {
        sidebarOpen.value = !sidebarOpen.value;

        return;
    }

    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem('sidebar-collapsed', sidebarCollapsed.value ? '1' : '0');
}

function can(permission) {
    return permissions.value.has(permission);
}

/**
 * Sidebar structure grouped by module. Every item needs a permission
 * except the dashboard itself.
 */
const nav = computed(() => {
    const groups = [
        {
            label: t('nav.sales'),
            items: [
                { label: t('nav.dashboard'), href: '/admin', icon: 'chart-line', permission: null, exact: true },
                { label: t('nav.pos'), href: '/admin/pos', icon: 'cash-register', permission: 'pos.access' },
                { label: t('nav.tables'), href: '/admin/tables', icon: 'bell-concierge', permission: 'tables.view' },
                { label: t('nav.reservations'), href: '/admin/reservations', icon: 'calendar-days', permission: 'reservations.view' },
                { label: t('nav.orders'), href: '/admin/orders', icon: 'receipt', permission: 'orders.view' },
                { label: t('nav.kds'), href: '/admin/kds', icon: 'utensils', permission: 'kds.access' },
            ],
        },
        {
            label: t('nav.inventory'),
            items: [
                { label: t('nav.menu'), href: '/admin/menu', icon: 'book-open', permission: 'menu.view' },
                { label: t('nav.media'), href: '/admin/media', icon: 'images', permission: 'media.view' },
                { label: t('nav.ingredients'), href: '/admin/ingredients', icon: 'boxes-stacked', permission: 'inventory.view' },
                { label: t('nav.recipes'), href: '/admin/recipes', icon: 'list-check', permission: 'recipes.manage' },
                { label: t('nav.purchases'), href: '/admin/purchases', icon: 'truck-fast', permission: 'purchases.manage' },
                { label: t('nav.wastage'), href: '/admin/wastage', icon: 'trash', permission: 'inventory.view' },
                { label: t('nav.stock_counts'), href: '/admin/stock-counts', icon: 'clipboard-list', permission: 'inventory.view' },
                { label: t('nav.transfers'), href: '/admin/stock-transfers', icon: 'right-left', permission: 'transfers.manage' },
            ],
        },
        {
            label: t('nav.people'),
            items: [
                { label: t('nav.customers'), href: '/admin/customers', icon: 'users', permission: 'customers.view' },
                { label: t('loyalty.title'), href: '/admin/loyalty', icon: 'gift', permission: 'loyalty.manage' },
                { label: t('coupons.title'), href: '/admin/coupons', icon: 'ticket', permission: 'coupons.manage' },
                { label: t('nav.staff'), href: '/admin/staff', icon: 'user-shield', permission: 'staff.view' },
                { label: t('nav.attendance'), href: '/admin/attendance', icon: 'clock', permission: 'attendance.manage' },
                { label: t('nav.letters'), href: '/admin/letters', icon: 'envelope', permission: 'letters.manage' },
                { label: t('nav.careers'), href: '/admin/careers', icon: 'briefcase', permission: 'careers.manage' },
                { label: t('nav.blog'), href: '/admin/blog', icon: 'newspaper', permission: 'blog.manage' },
                { label: t('nav.delivery'), href: '/admin/delivery', icon: 'motorcycle', permission: 'delivery.dispatch' },
            ],
        },
        {
            label: t('nav.finance'),
            items: [
                { label: t('nav.accounting'), href: '/admin/accounting', icon: 'scale-balanced', permission: 'accounting.view' },
                { label: t('nav.payroll'), href: '/admin/payroll', icon: 'money-bill-wave', permission: 'payroll.manage' },
                { label: t('nav.reports'), href: '/admin/reports', icon: 'chart-column', permission: 'reports.view' },
                { label: t('expenses.title'), href: '/admin/expenses', icon: 'wallet', permission: 'expenses.manage' },
            ],
        },
        {
            label: t('nav.settings_group'),
            items: [
                { label: t('nav.branches'), href: '/admin/branches', icon: 'building', permission: 'branches.view' },
                { label: t('aggregators.title'), href: '/admin/aggregators', icon: 'motorcycle', permission: 'aggregators.manage' },
                { label: t('nav.settings'), href: '/admin/settings', icon: 'gear', permission: 'settings.manage' },
                { label: t('sync_conflicts.title'), href: '/admin/sync-conflicts', icon: 'rotate', permission: 'sync.resolve' },
                { label: t('nav.activity_log'), href: '/admin/activity-log', icon: 'clock', permission: 'activity.view' },
            ],
        },
    ];

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => item.permission === null || can(item.permission)),
        }))
        .filter((group) => group.items.length > 0);
});

const isActive = (item) => {
    if (item.exact) {
        return page.url === item.href;
    }

    return page.url.startsWith(item.href);
};

const commands = computed(() => nav.value.flatMap((group) => group.items.map((item) => ({
    label: item.label,
    icon: item.icon,
    href: item.href,
}))));

const currentBranch = computed(() => {
    const branches = page.props.branches ?? [];
    const currentId = page.props.currentBranchId;

    return branches.find((branch) => branch.id === currentId) ?? branches[0];
});

function switchBranch(event) {
    router.post('/admin/branch-switch', { branch_id: event.target.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function logout() {
    router.post('/logout');
}

function openCommandPalette() {
    window.dispatchEvent(new CustomEvent('command-palette'));
}
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-x-0 bottom-0 top-(--demo-banner-h) z-30 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            class="fixed top-(--demo-banner-h) bottom-0 left-0 z-40 flex shrink-0 flex-col border-r border-border bg-surface transition-all duration-150 lg:static"
            :class="[
                sidebarCollapsed ? 'w-14' : 'w-[220px]',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="flex h-12 shrink-0 items-center gap-2 border-b border-border px-3">
                <BrandMark :size="28" />
                <span v-if="!sidebarCollapsed" class="truncate text-sm font-semibold"><BrandName :name="page.props.appName" /></span>
            </div>

            <nav class="thin-scrollbar flex-1 space-y-3 overflow-y-auto p-2" aria-label="Main">
                <div v-for="group in nav" :key="group.label">
                    <p
                        v-if="!sidebarCollapsed"
                        class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-wider text-muted"
                        :class="page.props.locale === 'bn' ? 'normal-case tracking-normal' : ''"
                    >
                        {{ group.label }}
                    </p>

                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex h-8 items-center gap-2 rounded-md px-2 text-[13px] transition-colors"
                                :class="isActive(item)
                                    ? 'bg-accent/15 font-medium text-foreground'
                                    : 'text-muted hover:bg-background hover:text-foreground'"
                                :title="sidebarCollapsed ? item.label : undefined"
                                @click="sidebarOpen = false"
                            >
                                <AppIcon :name="item.icon" size="14" class="shrink-0" />
                                <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <button
                type="button"
                class="hidden h-9 shrink-0 items-center justify-center border-t border-border text-muted hover:text-foreground lg:flex"
                :aria-label="t('nav.toggle_sidebar')"
                @click="toggleSidebar"
            >
                <AppIcon :name="sidebarCollapsed ? 'chevron-right' : 'chevron-left'" size="14" />
            </button>
        </aside>

        <!-- Main -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-(--demo-banner-h) z-30 flex h-12 shrink-0 items-center gap-2 border-b border-border bg-surface px-3">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-muted hover:text-foreground lg:hidden"
                    :aria-label="t('nav.toggle_sidebar')"
                    @click="toggleSidebar"
                >
                    <AppIcon name="bars" size="16" />
                </button>

                <!-- Branch switcher -->
                <label v-if="(page.props.branches ?? []).length > 1" class="flex items-center gap-1.5">
                    <span class="sr-only">{{ t('nav.branch') }}</span>
                    <select
                        class="h-7 max-w-40 truncate rounded-md border border-border bg-background px-1.5 text-[13px] text-foreground"
                        :value="currentBranch?.id"
                        @change="switchBranch"
                    >
                        <option v-for="branch in page.props.branches" :key="branch.id" :value="branch.id">
                            {{ branch.code }} - {{ page.props.locale === 'bn' && branch.name_bn ? branch.name_bn : branch.name }}
                        </option>
                    </select>
                </label>

                <span v-else-if="currentBranch" class="text-[13px] text-muted">{{ currentBranch.name }}</span>

                <button
                    type="button"
                    class="ml-auto hidden h-7 items-center gap-1.5 rounded-md border border-border bg-background px-2 text-[13px] text-muted hover:text-foreground md:flex"
                    :aria-label="t('common.command_hint')"
                    @click="openCommandPalette"
                >
                    <AppIcon name="magnifying-glass" size="12" />
                    <span class="text-xs">{{ t('common.search') }}</span>
                    <kbd class="rounded border border-border px-1 text-[10px]">Ctrl K</kbd>
                </button>

                <div class="ml-auto flex items-center gap-1 md:ml-0">
                    <SyncStatusPill />
                    <NotificationBell :notifications="page.props.notifications ?? []" />
                    <LanguageSwitch />
                    <ThemeToggle />

                    <div class="relative">
                        <button
                            type="button"
                            class="flex h-8 items-center gap-1.5 rounded-md px-1 hover:bg-background"
                            :aria-label="t('nav.account')"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                                {{ (user?.name ?? '?').slice(0, 2).toUpperCase() }}
                            </span>
                            <AppIcon name="chevron-down" size="10" class="text-muted" />
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 z-40 mt-1 w-48 rounded-lg border border-border bg-surface p-1 shadow-lg"
                            @click="userMenuOpen = false"
                        >
                            <div class="border-b border-border px-2.5 py-2">
                                <p class="truncate text-[13px] font-medium text-foreground">{{ user?.name }}</p>
                                <p class="truncate text-xs text-muted">{{ user?.email ?? user?.username }}</p>
                                <p class="mt-0.5 text-[11px] text-muted">{{ (user?.roles ?? []).join(', ') }}</p>
                            </div>

                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-foreground hover:bg-background"
                                @click="logout"
                            >
                                <AppIcon name="arrow-right-from-bracket" size="12" />
                                {{ t('nav.logout') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-3 md:p-4">
                <slot />
            </main>
        </div>

        <CommandPalette :commands="commands" />
    </div>
</template>
