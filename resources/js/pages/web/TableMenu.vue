<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { localized, photoSrc } from '@/lib/site';

const props = defineProps({
    token: { type: String, required: true },
    table: { type: Object, required: true },
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    ordering: { type: Object, default: () => ({ allowed: true, reason: null, until: null, message: null }) },
    placed: { type: Object, default: null },
});

// A reserved table, or one already being served, can be browsed but not ordered from.
const canOrder = computed(() => props.ordering.allowed !== false);

const closedText = computed(() => {
    if (props.ordering.reason === 'reserved' && props.ordering.until) {
        const until = new Date(props.ordering.until);
        const sameDay = until.toDateString() === new Date().toDateString();
        const time = until.toLocaleString(undefined, sameDay
            ? { hour: 'numeric', minute: '2-digit' }
            : { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });

        return t('tablemenu.table_reserved_until', { time });
    }

    return props.ordering.message ?? '';
});

const ITEMS_PER_PAGE = 5;
const CART_KEY = `dt-table-cart:${props.token.slice(0, 10)}`;

const opened = ref(props.placed !== null);
const pageIndex = ref(0);
const direction = ref('next');
const wide = ref(false);
const contentsOpen = ref(false);
const cartOpen = ref(false);
const sending = ref(false);
const sendError = ref('');
const justPlaced = ref(props.placed);
const cart = ref([]);
const guest = ref({ name: '', mobile: '', note: '' });
const selected = ref(null);
const choice = ref({ variantId: null, addOns: {}, quantity: 1 });

// ---- pages: a chapter is a category, split so each page holds a handful of dishes ----

const pages = computed(() => {
    const result = [];

    props.categories.forEach((category, chapter) => {
        const items = category.items ?? [];

        for (let start = 0; start < items.length; start += ITEMS_PER_PAGE) {
            result.push({
                chapter,
                category,
                first: start === 0,
                items: items.slice(start, start + ITEMS_PER_PAGE),
            });
        }
    });

    return result;
});

const spread = computed(() => (wide.value ? 2 : 1));
const visiblePages = computed(() => pages.value.slice(pageIndex.value, pageIndex.value + spread.value));
const lastStart = computed(() => Math.max(0, Math.ceil(pages.value.length / spread.value) * spread.value - spread.value));
const currentChapter = computed(() => visiblePages.value[0]?.chapter ?? 0);
const pageLabel = computed(() => t('tablemenu.page_of', { current: pageIndex.value + 1, total: pages.value.length }));

function turn(step) {
    const next = Math.min(lastStart.value, Math.max(0, pageIndex.value + step * spread.value));

    if (next !== pageIndex.value) {
        direction.value = step > 0 ? 'next' : 'prev';
        pageIndex.value = next;
    }
}

function goToChapter(chapter) {
    const target = pages.value.findIndex((page) => page.chapter === chapter);
    const next = Math.floor(Math.max(0, target) / spread.value) * spread.value;

    direction.value = next >= pageIndex.value ? 'next' : 'prev';
    pageIndex.value = next;
    contentsOpen.value = false;
}

let touchStart = null;

function onTouchStart(event) {
    touchStart = event.touches[0].clientX;
}

function onTouchEnd(event) {
    if (touchStart === null) {
        return;
    }

    const distance = event.changedTouches[0].clientX - touchStart;

    touchStart = null;

    if (Math.abs(distance) > 60) {
        turn(distance < 0 ? 1 : -1);
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        selected.value = null;
        cartOpen.value = false;
        contentsOpen.value = false;

        return;
    }

    if (! opened.value || selected.value || cartOpen.value || contentsOpen.value) {
        return;
    }

    if (event.key === 'ArrowRight') {
        turn(1);
    } else if (event.key === 'ArrowLeft') {
        turn(-1);
    }
}

// ---- dishes, sizes and extras ----

function sortedVariants(item) {
    return [...(item.variants ?? [])].sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0));
}

function activeAddOns(item) {
    return [...(item.add_ons ?? [])]
        .filter((addOn) => addOn.is_active !== false)
        .sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0));
}

function startingPrice(item) {
    if ((item.variants ?? []).length === 0) {
        return Number(item.base_price);
    }

    return Math.min(...item.variants.map((variant) => Number(variant.price)));
}

function itemPhoto(item) {
    return item.cover_url ?? photoSrc(item.photo_path);
}

function inCart(item) {
    return cart.value.filter((line) => line.item_id === item.id).reduce((sum, line) => sum + line.quantity, 0);
}

function openItem(item) {
    const variants = sortedVariants(item);
    const preferred = variants.find((variant) => variant.is_default) ?? variants[0] ?? null;

    choice.value = { variantId: preferred?.id ?? null, addOns: {}, quantity: 1 };
    selected.value = item;
}

const choiceVariant = computed(() => (selected.value ? (selected.value.variants ?? []).find((variant) => variant.id === choice.value.variantId) ?? null : null));

const choiceAddOns = computed(() => (selected.value
    ? activeAddOns(selected.value)
        .map((addOn) => ({ id: addOn.id, name: addOn.name, name_bn: addOn.name_bn, price: Number(addOn.price), qty: choice.value.addOns[addOn.id] ?? 0 }))
        .filter((addOn) => addOn.qty > 0)
    : []));

const choiceUnit = computed(() => {
    if (! selected.value) {
        return 0;
    }

    const base = Number(choiceVariant.value?.price ?? selected.value.base_price);

    return base + choiceAddOns.value.reduce((sum, addOn) => sum + addOn.price * addOn.qty, 0);
});

function setAddOn(id, quantity) {
    choice.value.addOns = { ...choice.value.addOns, [id]: Math.min(9, Math.max(0, quantity)) };
}

function lineKey(itemId, variantId, addOns) {
    return `${itemId}-${variantId ?? 0}-${[...addOns].sort((a, b) => a.id - b.id).map((addOn) => `${addOn.id}x${addOn.qty}`).join('.')}`;
}

function addToCart() {
    const item = selected.value;

    if (! item) {
        return;
    }

    const variant = choiceVariant.value;
    const addOns = choiceAddOns.value;
    const uid = lineKey(item.id, variant?.id ?? null, addOns);
    const existing = cart.value.find((line) => line.uid === uid);

    if (existing) {
        existing.quantity = Math.min(20, existing.quantity + choice.value.quantity);
    } else {
        cart.value.push({
            uid,
            item_id: item.id,
            name: item.name,
            name_bn: item.name_bn,
            variant_id: variant?.id ?? null,
            variant_name: variant?.name ?? null,
            variant_name_bn: variant?.name_bn ?? null,
            unit: choiceUnit.value,
            add_ons: addOns,
            quantity: choice.value.quantity,
        });
    }

    selected.value = null;
}

// ---- the order ----

const itemCount = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0));
const subtotal = computed(() => cart.value.reduce((sum, line) => sum + line.unit * line.quantity, 0));
const serviceCharge = computed(() => subtotal.value * Number(props.branch.service_charge_percent) / 100);
const vatAmount = computed(() => {
    const rate = Number(props.branch.vat_percent);
    const base = subtotal.value + serviceCharge.value;

    return props.branch.vat_inclusive ? base - base * 100 / (100 + rate) : base * rate / 100;
});
const total = computed(() => Math.round(props.branch.vat_inclusive ? subtotal.value + serviceCharge.value : subtotal.value + serviceCharge.value + vatAmount.value));

function changeQuantity(line, step) {
    line.quantity = Math.min(20, line.quantity + step);

    if (line.quantity <= 0) {
        cart.value = cart.value.filter((entry) => entry.uid !== line.uid);
    }
}

function placeOrder() {
    if (sending.value || cart.value.length === 0) {
        return;
    }

    sending.value = true;
    sendError.value = '';

    router.post(route('web.table.order', { token: props.token }), {
        items: cart.value.map((line) => ({
            menu_item_id: line.item_id,
            variant_id: line.variant_id,
            quantity: line.quantity,
            add_ons: line.add_ons.map((addOn) => ({ id: addOn.id, qty: addOn.qty })),
        })),
        guest_name: guest.value.name || null,
        guest_mobile: guest.value.mobile || null,
        note: guest.value.note || null,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            sendError.value = String(Object.values(errors)[0] ?? t('tablemenu.order_failed'));
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

watch(() => props.placed, (placed) => {
    if (placed) {
        justPlaced.value = placed;
        cart.value = [];
        cartOpen.value = false;
        guest.value.note = '';
        opened.value = true;
    }
}, { immediate: true });

// The order survives a refresh: it is kept on this phone, never on the server.
watch(cart, (lines) => {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(lines));
    } catch {
        // storage unavailable
    }
}, { deep: true });

function openBook() {
    direction.value = 'next';
    opened.value = true;
}

function lineName(line) {
    return localized(line, 'name');
}

function updateWide() {
    wide.value = window.matchMedia('(min-width: 1024px)').matches;
    pageIndex.value = Math.floor(pageIndex.value / spread.value) * spread.value;
}

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(CART_KEY) ?? '[]');

        if (Array.isArray(saved) && ! props.placed) {
            cart.value = saved;
        }
    } catch {
        // ignore a damaged copy
    }

    updateWide();
    window.addEventListener('resize', updateWide);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateWide);
    window.removeEventListener('keydown', onKeydown);
});

// Keep the tab bar showing the chapter that is open.
watch(currentChapter, () => nextTick(() => document.querySelector('[data-chapter-tab][aria-selected="true"]')?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })));
</script>

<template>
    <div class="tm-desk">
        <Head>
            <title>{{ t('tablemenu.table', { name: table.name }) }} - {{ localized(branch, 'name') }}</title>
            <meta name="robots" content="noindex,nofollow">
        </Head>

        <header class="tm-top">
            <div class="min-w-0">
                <p class="truncate font-serif text-sm font-semibold tracking-wide text-[#f4ead2]">{{ localized(branch, 'name') }}</p>
                <p class="flex items-center gap-1 text-[11px] text-[#d9b25a]">
                    <AppIcon name="shield-halved" size="10" />
                    {{ t('tablemenu.secure_note') }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span class="rounded-full border border-[#b8892f] bg-[#2c1810] px-3 py-1 font-serif text-xs font-semibold text-[#f4ead2]">{{ t('tablemenu.table', { name: table.name }) }}</span>
                <LanguageSwitch />
            </div>
        </header>

        <!-- Cover -->
        <main v-if="! opened" class="tm-stage">
            <section class="tm-cover" aria-labelledby="cover-title">
                <div class="tm-cover-frame">
                    <span class="tm-emblem"><AppIcon name="utensils" size="26" /></span>
                    <p class="tm-kicker">{{ t('tablemenu.welcome') }}</p>
                    <h1 id="cover-title" class="tm-cover-title">{{ localized(branch, 'name') }}</h1>
                    <div class="tm-rule" />
                    <p class="tm-menu-word">{{ t('tablemenu.menu_word') }}</p>
                    <p class="tm-cover-line">{{ t('tablemenu.welcome_line') }}</p>
                    <p class="tm-ribbon">{{ t('tablemenu.table', { name: table.name }) }}<span v-if="table.area"> - {{ table.area }}</span></p>
                    <p v-if="! canOrder" class="tm-closed" role="status">
                        <AppIcon name="bell-concierge" size="14" />
                        <span>{{ closedText }}</span>
                    </p>
                    <button type="button" class="tm-open" @click="openBook">
                        <AppIcon name="book-open" size="16" />
                        {{ t('tablemenu.open_book') }}
                    </button>
                </div>
            </section>
        </main>

        <!-- The book -->
        <main v-else class="tm-stage tm-stage-book" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
            <p v-if="! canOrder" class="tm-closed tm-closed-bar" role="status">
                <AppIcon name="bell-concierge" size="14" />
                <span>{{ closedText }}</span>
            </p>

            <nav class="tm-tabs" :aria-label="t('tablemenu.chapters')">
                <button
                    v-for="(category, chapter) in categories"
                    :key="category.id"
                    type="button"
                    data-chapter-tab
                    class="tm-tab"
                    :aria-selected="currentChapter === chapter"
                    @click="goToChapter(chapter)"
                >
                    {{ localized(category, 'name') }}
                </button>
            </nav>

            <div class="tm-book" :class="wide ? 'tm-book-wide' : ''">
                <div :key="`${pageIndex}-${direction}`" class="tm-spread" :class="[wide ? 'tm-spread-wide' : '', `tm-turn-${direction}`]">
                    <article v-for="(page, index) in visiblePages" :key="`${page.chapter}-${pageIndex + index}`" class="tm-page" :class="index === 0 ? 'tm-page-left' : 'tm-page-right'">
                        <header class="tm-page-head">
                            <h2 :class="page.first ? 'tm-chapter' : 'tm-chapter tm-chapter-cont'">{{ localized(page.category, 'name') }}</h2>
                            <div class="tm-rule tm-rule-dark" />
                        </header>

                        <ul class="tm-list">
                            <li v-for="item in page.items" :key="item.id">
                                <button type="button" class="tm-item" @click="openItem(item)">
                                    <span class="tm-thumb">
                                        <img v-if="itemPhoto(item)" :src="itemPhoto(item)" :alt="localized(item, 'name')" loading="lazy">
                                        <AppIcon v-else name="utensils" size="18" />
                                    </span>
                                    <span class="tm-item-body">
                                        <span class="tm-item-line">
                                            <span class="tm-name">
                                                <span v-if="item.is_vegetarian" class="tm-veg" :title="t('tablemenu.vegetarian')" />
                                                {{ localized(item, 'name') }}
                                            </span>
                                            <span class="tm-leader" aria-hidden="true" />
                                            <span class="tm-price">
                                                <span v-if="(item.variants ?? []).length" class="tm-from">{{ t('tablemenu.from') }}</span>
                                                {{ formatMoney(startingPrice(item), { decimals: 0 }) }}
                                            </span>
                                        </span>
                                        <span v-if="localized(item, 'description')" class="tm-desc">{{ localized(item, 'description') }}</span>
                                    </span>
                                    <span v-if="inCart(item) > 0" class="tm-count">{{ inCart(item) }}</span>
                                    <span v-else-if="canOrder" class="tm-plus"><AppIcon name="plus" size="12" /></span>
                                </button>
                            </li>
                        </ul>

                        <footer class="tm-folio">{{ pageIndex + index + 1 }}</footer>
                    </article>
                </div>
            </div>
        </main>

        <!-- Page controls and order button -->
        <div v-if="opened" class="tm-dock">
            <div class="tm-dock-nav">
                <button type="button" class="tm-round" :disabled="pageIndex === 0" :aria-label="t('tablemenu.previous')" @click="turn(-1)">
                    <AppIcon name="chevron-left" size="16" />
                </button>
                <button type="button" class="tm-pagebtn" @click="contentsOpen = true">
                    <AppIcon name="book-open" size="13" />
                    <span>{{ pageLabel }}</span>
                </button>
                <button type="button" class="tm-round" :disabled="pageIndex >= lastStart" :aria-label="t('tablemenu.next')" @click="turn(1)">
                    <AppIcon name="chevron-right" size="16" />
                </button>
            </div>

            <span v-if="! canOrder" class="tm-cartbtn tm-cartbtn-idle tm-cartbtn-off">{{ t('tablemenu.browse_only') }}</span>
            <button v-else type="button" class="tm-cartbtn" :class="itemCount === 0 ? 'tm-cartbtn-idle' : ''" @click="cartOpen = true">
                <AppIcon name="bag-shopping" size="16" />
                <span class="tm-cartbtn-text">{{ itemCount === 0 ? t('tablemenu.your_order') : t('tablemenu.view_order') }}</span>
                <span v-if="itemCount > 0" class="tm-cartbtn-badge">{{ itemCount }}</span>
                <span v-if="itemCount > 0" class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</span>
            </button>
        </div>

        <!-- Contents -->
        <Teleport to="body">
            <div v-if="contentsOpen" class="tm-overlay" @click.self="contentsOpen = false">
                <section class="tm-sheet tm-sheet-paper" role="dialog" aria-modal="true" :aria-label="t('tablemenu.contents')">
                    <header class="tm-sheet-head">
                        <h2 class="tm-chapter">{{ t('tablemenu.contents') }}</h2>
                        <button type="button" class="tm-x" :aria-label="t('tablemenu.close')" @click="contentsOpen = false"><AppIcon name="xmark" size="14" /></button>
                    </header>
                    <ol class="tm-contents">
                        <li v-for="(category, chapter) in categories" :key="category.id">
                            <button type="button" :class="currentChapter === chapter ? 'tm-contents-row tm-contents-current' : 'tm-contents-row'" @click="goToChapter(chapter)">
                                <span class="tm-contents-name">{{ localized(category, 'name') }}</span>
                                <span class="tm-leader" aria-hidden="true" />
                                <span class="tm-contents-page">{{ pages.findIndex((page) => page.chapter === chapter) + 1 }}</span>
                            </button>
                        </li>
                    </ol>
                </section>
            </div>
        </Teleport>

        <!-- A dish -->
        <Teleport to="body">
            <div v-if="selected" class="tm-overlay" @click.self="selected = null">
                <section class="tm-sheet tm-sheet-paper" role="dialog" aria-modal="true" :aria-label="localized(selected, 'name')">
                    <div v-if="itemPhoto(selected)" class="tm-hero"><img :src="itemPhoto(selected)" :alt="localized(selected, 'name')"></div>
                    <header class="tm-sheet-head">
                        <div class="min-w-0">
                            <h2 class="tm-chapter">{{ localized(selected, 'name') }}</h2>
                            <p v-if="localized(selected, 'description')" class="tm-desc tm-desc-full">{{ localized(selected, 'description') }}</p>
                        </div>
                        <button type="button" class="tm-x" :aria-label="t('tablemenu.close')" @click="selected = null"><AppIcon name="xmark" size="14" /></button>
                    </header>

                    <div class="tm-sheet-body">
                        <div v-if="(selected.variants ?? []).length">
                            <h3 class="tm-label">{{ t('tablemenu.choose_size') }}</h3>
                            <div class="tm-choices" role="radiogroup">
                                <button
                                    v-for="variant in sortedVariants(selected)"
                                    :key="variant.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="choice.variantId === variant.id"
                                    class="tm-choice"
                                    :class="canOrder && choice.variantId === variant.id ? 'tm-choice-on' : ''"
                                    :disabled="! canOrder"
                                    @click="choice.variantId = variant.id"
                                >
                                    <span>{{ localized(variant, 'name') }}</span>
                                    <span class="tabular-nums">{{ formatMoney(variant.price, { decimals: 0 }) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="activeAddOns(selected).length">
                            <h3 class="tm-label">{{ t('tablemenu.add_ons') }}</h3>
                            <ul class="tm-addons">
                                <li v-for="addOn in activeAddOns(selected)" :key="addOn.id" class="tm-addon" :class="(choice.addOns[addOn.id] ?? 0) > 0 ? 'tm-addon-on' : ''">
                                    <span class="min-w-0 flex-1 truncate">{{ localized(addOn, 'name') }}</span>
                                    <span class="tm-addon-price tabular-nums">+ {{ formatMoney(addOn.price, { decimals: 0 }) }}</span>
                                    <span v-if="canOrder" class="tm-stepper">
                                        <button type="button" :disabled="(choice.addOns[addOn.id] ?? 0) === 0" :aria-label="t('tablemenu.remove')" @click="setAddOn(addOn.id, (choice.addOns[addOn.id] ?? 0) - 1)"><AppIcon name="minus" size="11" /></button>
                                        <span class="tabular-nums">{{ choice.addOns[addOn.id] ?? 0 }}</span>
                                        <button type="button" :aria-label="t('tablemenu.add')" @click="setAddOn(addOn.id, (choice.addOns[addOn.id] ?? 0) + 1)"><AppIcon name="plus" size="11" /></button>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <footer v-if="canOrder" class="tm-sheet-foot">
                        <span class="tm-stepper tm-stepper-big" :aria-label="t('tablemenu.quantity')">
                            <button type="button" :disabled="choice.quantity <= 1" @click="choice.quantity -= 1"><AppIcon name="minus" size="13" /></button>
                            <span class="tabular-nums">{{ choice.quantity }}</span>
                            <button type="button" :disabled="choice.quantity >= 20" @click="choice.quantity += 1"><AppIcon name="plus" size="13" /></button>
                        </span>
                        <button type="button" class="tm-primary" @click="addToCart">
                            <span>{{ t('tablemenu.add_to_order') }}</span>
                            <span class="tabular-nums">{{ formatMoney(choiceUnit * choice.quantity, { decimals: 0 }) }}</span>
                        </button>
                    </footer>
                </section>
            </div>
        </Teleport>

        <!-- The order -->
        <Teleport to="body">
            <div v-if="cartOpen" class="tm-overlay" @click.self="cartOpen = false">
                <section class="tm-sheet tm-sheet-paper" role="dialog" aria-modal="true" :aria-label="t('tablemenu.your_order')">
                    <header class="tm-sheet-head">
                        <div>
                            <h2 class="tm-chapter">{{ t('tablemenu.your_order') }}</h2>
                            <p class="tm-desc">{{ t('tablemenu.table', { name: table.name }) }}</p>
                        </div>
                        <button type="button" class="tm-x" :aria-label="t('tablemenu.close')" @click="cartOpen = false"><AppIcon name="xmark" size="14" /></button>
                    </header>

                    <div class="tm-sheet-body">
                        <p v-if="cart.length === 0" class="tm-empty">{{ t('tablemenu.cart_empty') }}</p>

                        <ul v-else class="tm-lines">
                            <li v-for="line in cart" :key="line.uid" class="tm-line">
                                <div class="min-w-0 flex-1">
                                    <p class="tm-line-name">{{ lineName(line) }}<span v-if="line.variant_name" class="tm-line-variant"> - {{ localized({ name: line.variant_name, name_bn: line.variant_name_bn }, 'name') }}</span></p>
                                    <p v-if="line.add_ons.length" class="tm-line-extras">{{ line.add_ons.map((addOn) => `${addOn.qty > 1 ? addOn.qty + 'x ' : '+ '}${localized(addOn, 'name')}`).join(', ') }}</p>
                                    <p class="tm-line-price tabular-nums">{{ formatMoney(line.unit * line.quantity, { decimals: 0 }) }}</p>
                                </div>
                                <span class="tm-stepper">
                                    <button type="button" :aria-label="t('tablemenu.remove')" @click="changeQuantity(line, -1)"><AppIcon name="minus" size="11" /></button>
                                    <span class="tabular-nums">{{ line.quantity }}</span>
                                    <button type="button" :aria-label="t('tablemenu.add')" :disabled="line.quantity >= 20" @click="changeQuantity(line, 1)"><AppIcon name="plus" size="11" /></button>
                                </span>
                            </li>
                        </ul>

                        <template v-if="cart.length">
                            <label class="tm-field">
                                <span class="tm-label">{{ t('tablemenu.note') }}</span>
                                <textarea v-model="guest.note" rows="2" maxlength="200" :placeholder="t('tablemenu.note_placeholder')" />
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="tm-field">
                                    <span class="tm-label">{{ t('tablemenu.guest_name') }}</span>
                                    <input v-model="guest.name" type="text" maxlength="60" autocomplete="name">
                                </label>
                                <label class="tm-field">
                                    <span class="tm-label">{{ t('tablemenu.guest_mobile') }}</span>
                                    <input v-model="guest.mobile" type="tel" maxlength="20" inputmode="tel" autocomplete="tel">
                                </label>
                            </div>

                            <dl class="tm-totals">
                                <div><dt>{{ t('tablemenu.subtotal') }}</dt><dd class="tabular-nums">{{ formatMoney(subtotal, { decimals: 0 }) }}</dd></div>
                                <div v-if="serviceCharge > 0"><dt>{{ t('tablemenu.service') }}</dt><dd class="tabular-nums">{{ formatMoney(serviceCharge, { decimals: 0 }) }}</dd></div>
                                <div v-if="vatAmount > 0"><dt>{{ t('tablemenu.vat') }}</dt><dd class="tabular-nums">{{ formatMoney(vatAmount, { decimals: 0 }) }}</dd></div>
                                <div class="tm-totals-grand"><dt>{{ t('tablemenu.total') }}</dt><dd class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</dd></div>
                            </dl>
                            <p class="tm-desc">{{ t('tablemenu.estimate_hint') }}</p>
                            <p v-if="sendError" class="tm-error" role="alert">{{ sendError }}</p>
                        </template>
                    </div>

                    <footer v-if="cart.length" class="tm-sheet-foot">
                        <button type="button" class="tm-primary tm-primary-wide" :disabled="sending" @click="placeOrder">
                            <AppIcon name="bell-concierge" size="16" />
                            <span>{{ sending ? t('tablemenu.sending') : t('tablemenu.place_order') }}</span>
                            <span class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</span>
                        </button>
                    </footer>
                </section>
            </div>
        </Teleport>

        <!-- Order sent -->
        <Teleport to="body">
            <div v-if="justPlaced" class="tm-overlay tm-overlay-center">
                <section class="tm-sheet tm-sheet-paper tm-done" role="dialog" aria-modal="true" :aria-label="t('tablemenu.order_sent')">
                    <span class="tm-done-mark"><AppIcon name="circle-check" size="34" /></span>
                    <h2 class="tm-chapter tm-center">{{ t('tablemenu.order_sent') }}</h2>
                    <p class="tm-desc tm-center">{{ t('tablemenu.order_sent_line', { table: table.name }) }}</p>
                    <p class="tm-done-number">
                        <span class="tm-label">{{ t('tablemenu.order_number') }}</span>
                        <strong>{{ justPlaced.invoice_no }}</strong>
                    </p>
                    <a :href="justPlaced.track" class="tm-link">{{ t('tablemenu.track_order') }}</a>
                    <button type="button" class="tm-primary tm-primary-wide" @click="justPlaced = null">{{ t('tablemenu.order_more') }}</button>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<script>
export default { layout: null };
</script>

<style scoped>
.tm-desk {
    --paper: #fbf6ea;
    --paper-edge: #e4d6b3;
    --ink: #2b2118;
    --ink-soft: #6d5b45;
    --gold: #b8892f;
    --gold-light: #d9b25a;
    --leather: #2c1810;
    --leather-light: #3b2418;
    --green: #2f7d4f;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(ellipse at 50% 0%, rgba(184, 137, 47, 0.18), transparent 60%),
        linear-gradient(180deg, var(--leather-light), var(--leather));
    color: var(--ink);
    font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif;
}

h1,
h2,
h3 {
    font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif;
}

.tm-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(184, 137, 47, 0.35);
}

.tm-stage {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 0.75rem 7.5rem;
}

.tm-stage-book {
    padding-top: 0.5rem;
}

/* ---- cover ---- */
.tm-cover {
    width: 100%;
    max-width: 26rem;
    margin: auto;
}

.tm-cover-frame {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.5rem;
    padding: 2.5rem 1.5rem 2rem;
    border-radius: 1rem;
    background: linear-gradient(145deg, #5a3622, #34200f);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.55), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
    color: #f4ead2;
}

.tm-cover-frame::before {
    content: '';
    position: absolute;
    inset: 0.6rem;
    border: 1px solid var(--gold);
    border-radius: 0.65rem;
    pointer-events: none;
}

.tm-emblem {
    display: flex;
    width: 3.75rem;
    height: 3.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--gold);
    color: var(--gold-light);
    background: rgba(0, 0, 0, 0.18);
}

.tm-kicker {
    margin-top: 0.5rem;
    font-size: 0.7rem;
    letter-spacing: 0.35em;
    text-transform: uppercase;
    color: var(--gold-light);
}

.tm-cover-title {
    font-size: 1.9rem;
    line-height: 1.15;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.tm-menu-word {
    font-size: 1.1rem;
    letter-spacing: 0.5em;
    text-transform: uppercase;
    color: var(--gold-light);
}

.tm-cover-line {
    max-width: 18rem;
    font-size: 0.9rem;
    line-height: 1.5;
    color: rgba(244, 234, 210, 0.78);
}

.tm-ribbon {
    margin-top: 0.5rem;
    padding: 0.35rem 1.25rem;
    border-top: 1px solid var(--gold);
    border-bottom: 1px solid var(--gold);
    font-size: 0.95rem;
    letter-spacing: 0.08em;
    color: #f4ead2;
}

.tm-open {
    margin-top: 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 2.9rem;
    padding: 0 1.6rem;
    border-radius: 9999px;
    background: linear-gradient(180deg, var(--gold-light), var(--gold));
    color: #2c1810;
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
    transition: transform 0.15s ease;
}

.tm-open:hover {
    transform: translateY(-1px);
}

.tm-rule {
    height: 1px;
    width: 7rem;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
}

.tm-rule-dark {
    width: 100%;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
}

.tm-closed {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    max-width: 22rem;
    margin-top: 0.75rem;
    padding: 0.65rem 0.9rem;
    border: 1px solid var(--gold);
    border-radius: 0.7rem;
    background: rgba(184, 137, 47, 0.14);
    text-align: left;
    font-size: 0.82rem;
    line-height: 1.45;
    color: #f4ead2;
}

.tm-closed svg {
    flex-shrink: 0;
    margin-top: 0.15rem;
    color: var(--gold-light);
}

.tm-closed-bar {
    width: 100%;
    max-width: 62rem;
    margin: 0 0 0.4rem;
}

.tm-cartbtn-off {
    pointer-events: none;
    opacity: 0.8;
}

/* ---- chapter tabs ---- */
.tm-tabs {
    display: flex;
    gap: 0.4rem;
    width: 100%;
    max-width: 62rem;
    overflow-x: auto;
    padding: 0.4rem 0.25rem 0.6rem;
    scrollbar-width: none;
}

.tm-tabs::-webkit-scrollbar {
    display: none;
}

.tm-tab {
    flex-shrink: 0;
    padding: 0.35rem 0.9rem;
    border-radius: 0.5rem 0.5rem 0 0;
    border: 1px solid rgba(184, 137, 47, 0.5);
    background: rgba(0, 0, 0, 0.25);
    color: #e8d9b5;
    font-size: 0.8rem;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.tm-tab[aria-selected='true'] {
    background: var(--paper);
    color: var(--ink);
    border-color: var(--paper-edge);
    font-weight: 700;
}

/* ---- book ---- */
.tm-book {
    width: 100%;
    max-width: 34rem;
    perspective: 1800px;
}

.tm-book-wide {
    max-width: 62rem;
}

.tm-spread {
    display: grid;
    grid-template-columns: 1fr;
    transform-origin: left center;
    backface-visibility: hidden;
}

.tm-spread-wide {
    grid-template-columns: 1fr 1fr;
    position: relative;
}

.tm-spread-wide::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 50%;
    width: 3rem;
    transform: translateX(-50%);
    background: linear-gradient(90deg, transparent, rgba(60, 40, 20, 0.28), transparent);
    pointer-events: none;
}

.tm-turn-next {
    animation: turn-next 0.55s cubic-bezier(0.3, 0.7, 0.2, 1);
}

.tm-turn-prev {
    animation: turn-prev 0.55s cubic-bezier(0.3, 0.7, 0.2, 1);
}

@keyframes turn-next {
    from { transform: rotateY(-38deg) translateX(1.5rem); opacity: 0.2; }
    to { transform: rotateY(0) translateX(0); opacity: 1; }
}

@keyframes turn-prev {
    from { transform: rotateY(38deg) translateX(-1.5rem); opacity: 0.2; }
    to { transform: rotateY(0) translateX(0); opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
    .tm-turn-next,
    .tm-turn-prev {
        animation: none;
    }
}

.tm-page {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 31rem;
    padding: 1.4rem 1.25rem 1rem;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.5), transparent 25%),
        var(--paper);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45), inset 0 0 0 1px var(--paper-edge), inset 0 0 0 7px var(--paper), inset 0 0 0 8px var(--paper-edge);
}

.tm-spread:not(.tm-spread-wide) .tm-page {
    border-radius: 0.35rem 0.9rem 0.9rem 0.35rem;
}

.tm-page-left {
    border-radius: 0.9rem 0 0 0.9rem;
}

.tm-page-right {
    border-radius: 0 0.9rem 0.9rem 0;
}

.tm-page-head {
    text-align: center;
}

.tm-chapter {
    font-size: 1.35rem;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--ink);
}

.tm-chapter-cont {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--ink-soft);
}

.tm-center {
    text-align: center;
}

.tm-page-head .tm-rule {
    margin: 0.55rem auto 0.4rem;
}

.tm-list {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    margin-top: 0.3rem;
}

.tm-item {
    position: relative;
    display: flex;
    width: 100%;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.65rem 0.25rem;
    text-align: left;
    border-radius: 0.5rem;
    transition: background-color 0.15s ease;
}

.tm-item:hover,
.tm-item:focus-visible {
    background: rgba(184, 137, 47, 0.1);
    outline: none;
}

.tm-thumb {
    display: flex;
    width: 3.4rem;
    height: 3.4rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 0.5rem;
    border: 1px solid var(--paper-edge);
    background: #efe3c6;
    color: var(--gold);
}

.tm-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: sepia(0.18) saturate(1.05);
}

.tm-item-body {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 0.2rem;
}

.tm-item-line {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
}

.tm-name {
    display: inline-flex;
    align-items: baseline;
    gap: 0.4rem;
    font-size: 1.02rem;
    font-weight: 700;
    line-height: 1.25;
    color: var(--ink);
}

.tm-leader {
    flex: 1;
    min-width: 0.75rem;
    border-bottom: 2px dotted rgba(109, 91, 69, 0.55);
    transform: translateY(-0.28rem);
}

.tm-price {
    font-size: 1rem;
    font-weight: 700;
    white-space: nowrap;
    color: var(--ink);
}

.tm-from {
    margin-right: 0.2rem;
    font-size: 0.7rem;
    font-weight: 500;
    font-style: italic;
    color: var(--ink-soft);
}

.tm-desc {
    font-size: 0.8rem;
    font-style: italic;
    line-height: 1.4;
    color: var(--ink-soft);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tm-desc-full {
    -webkit-line-clamp: unset;
    display: block;
    margin-top: 0.2rem;
}

.tm-veg {
    display: inline-block;
    width: 0.8rem;
    height: 0.8rem;
    flex-shrink: 0;
    border: 1.5px solid var(--green);
    border-radius: 0.15rem;
    position: relative;
    transform: translateY(0.1rem);
}

.tm-veg::after {
    content: '';
    position: absolute;
    inset: 0.14rem;
    border-radius: 9999px;
    background: var(--green);
}

.tm-plus,
.tm-count {
    display: flex;
    width: 1.7rem;
    height: 1.7rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    align-self: center;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.tm-plus {
    border: 1px solid var(--gold);
    color: var(--gold);
}

.tm-count {
    background: var(--gold);
    color: #fff;
}

.tm-folio {
    margin-top: auto;
    padding-top: 0.6rem;
    text-align: center;
    font-size: 0.8rem;
    letter-spacing: 0.15em;
    color: var(--ink-soft);
}

/* ---- dock ---- */
.tm-dock {
    position: fixed;
    inset: auto 0 0 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.7rem 0.9rem calc(0.7rem + env(safe-area-inset-bottom));
    background: linear-gradient(0deg, rgba(28, 16, 9, 0.97) 70%, transparent);
}

.tm-dock-nav {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.tm-round {
    display: flex;
    width: 2.7rem;
    height: 2.7rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--gold);
    background: rgba(0, 0, 0, 0.35);
    color: #f4ead2;
}

.tm-round:disabled {
    opacity: 0.35;
}

.tm-pagebtn {
    display: inline-flex;
    height: 2.7rem;
    align-items: center;
    gap: 0.4rem;
    padding: 0 0.9rem;
    border-radius: 9999px;
    border: 1px solid rgba(184, 137, 47, 0.55);
    color: #f4ead2;
    font-size: 0.8rem;
    white-space: nowrap;
}

.tm-cartbtn {
    display: inline-flex;
    min-height: 2.9rem;
    align-items: center;
    gap: 0.5rem;
    padding: 0 1.1rem;
    border-radius: 9999px;
    background: linear-gradient(180deg, var(--gold-light), var(--gold));
    color: #2c1810;
    font-weight: 700;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.45);
}

.tm-cartbtn-idle {
    background: transparent;
    border: 1px solid var(--gold);
    color: #f4ead2;
    box-shadow: none;
}

.tm-cartbtn-badge {
    display: inline-flex;
    min-width: 1.4rem;
    height: 1.4rem;
    align-items: center;
    justify-content: center;
    padding: 0 0.3rem;
    border-radius: 9999px;
    background: #2c1810;
    color: var(--gold-light);
    font-size: 0.75rem;
}

@media (max-width: 420px) {
    .tm-cartbtn-text {
        display: none;
    }

    .tm-pagebtn span {
        display: none;
    }
}

/* ---- sheets ---- */
.tm-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    background: rgba(20, 10, 4, 0.7);
    backdrop-filter: blur(3px);
    font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif;
}

.tm-overlay-center {
    align-items: center;
    padding: 1rem;
}

.tm-sheet {
    display: flex;
    width: 100%;
    max-width: 30rem;
    max-height: 92vh;
    flex-direction: column;
    overflow: hidden;
    border-radius: 1.1rem 1.1rem 0 0;
    animation: sheet-up 0.28s ease-out;
}

.tm-overlay-center .tm-sheet {
    border-radius: 1.1rem;
}

.tm-sheet-paper {
    background: var(--paper, #fbf6ea);
    color: #2b2118;
    box-shadow: 0 -20px 50px rgba(0, 0, 0, 0.5), inset 0 0 0 1px #e4d6b3;
}

@keyframes sheet-up {
    from { transform: translateY(2rem); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.tm-hero {
    height: 11rem;
    flex-shrink: 0;
    overflow: hidden;
}

.tm-hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: sepia(0.12);
}

.tm-sheet-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.1rem 0.5rem;
}

.tm-x {
    display: flex;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid #e4d6b3;
    color: #6d5b45;
}

.tm-sheet-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.9rem;
    overflow-y: auto;
    padding: 0.4rem 1.1rem 1rem;
}

.tm-sheet-foot {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 1.1rem calc(0.8rem + env(safe-area-inset-bottom));
    border-top: 1px solid #e4d6b3;
    background: #f6eedb;
}

.tm-label {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #6d5b45;
}

.tm-choices {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
    gap: 0.5rem;
}

.tm-choice {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.65rem 0.8rem;
    border-radius: 0.6rem;
    border: 1px solid #e4d6b3;
    background: #fffdf7;
    font-weight: 600;
    text-align: left;
}

.tm-choice-on {
    border-color: #b8892f;
    background: #f7ecd0;
    box-shadow: inset 0 0 0 1px #b8892f;
}

.tm-addons {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.tm-addon {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.7rem;
    border-radius: 0.6rem;
    border: 1px solid #e4d6b3;
    background: #fffdf7;
}

.tm-addon-on {
    border-color: #b8892f;
    background: #f7ecd0;
}

.tm-addon-price {
    font-size: 0.85rem;
    color: #6d5b45;
}

.tm-stepper {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
    border-radius: 9999px;
    border: 1px solid #b8892f;
    background: #fffdf7;
    font-weight: 700;
}

.tm-stepper button {
    display: flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    color: #8a6420;
}

.tm-stepper button:disabled {
    opacity: 0.3;
}

.tm-stepper > span {
    min-width: 1.5rem;
    text-align: center;
}

.tm-stepper-big button {
    width: 2.6rem;
    height: 2.6rem;
}

.tm-primary {
    display: inline-flex;
    min-height: 2.9rem;
    flex: 1;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0 1.1rem;
    border-radius: 9999px;
    background: linear-gradient(180deg, #4a2c1c, #2c1810);
    color: #f4ead2;
    font-weight: 700;
    box-shadow: 0 6px 16px rgba(44, 24, 16, 0.35);
}

.tm-primary:disabled {
    opacity: 0.6;
}

.tm-primary-wide {
    justify-content: center;
}

.tm-primary-wide span:last-child {
    margin-left: auto;
}

.tm-contents {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    overflow-y: auto;
    padding: 0.25rem 1.1rem 1.25rem;
}

.tm-contents-row {
    display: flex;
    width: 100%;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.55rem 0.4rem;
    border-radius: 0.4rem;
    text-align: left;
    font-size: 1rem;
}

.tm-contents-row:hover {
    background: rgba(184, 137, 47, 0.12);
}

.tm-contents-current .tm-contents-name {
    font-weight: 700;
}

.tm-contents-page {
    font-variant-numeric: tabular-nums;
    color: #6d5b45;
}

.tm-empty {
    padding: 1.5rem 0.5rem;
    text-align: center;
    font-style: italic;
    color: #6d5b45;
}

.tm-lines {
    display: flex;
    flex-direction: column;
}

.tm-line {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 0;
    border-bottom: 1px dotted rgba(109, 91, 69, 0.5);
}

.tm-line-name {
    font-weight: 700;
}

.tm-line-variant {
    font-weight: 500;
    color: #6d5b45;
}

.tm-line-extras {
    font-size: 0.8rem;
    font-style: italic;
    color: #6d5b45;
}

.tm-line-price {
    font-size: 0.9rem;
    color: #2b2118;
}

.tm-field {
    display: flex;
    flex-direction: column;
}

.tm-field input,
.tm-field textarea {
    width: 100%;
    padding: 0.55rem 0.7rem;
    border-radius: 0.6rem;
    border: 1px solid #d9c9a3;
    background: #fffdf7;
    font-family: inherit;
    font-size: 0.95rem;
    color: #2b2118;
}

.tm-field input:focus,
.tm-field textarea:focus {
    outline: none;
    border-color: #b8892f;
    box-shadow: 0 0 0 2px rgba(184, 137, 47, 0.25);
}

.tm-totals {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.7rem 0.2rem 0;
    border-top: 1px solid #e4d6b3;
}

.tm-totals > div {
    display: flex;
    justify-content: space-between;
}

.tm-totals-grand {
    margin-top: 0.2rem;
    padding-top: 0.4rem;
    border-top: 1px dotted rgba(109, 91, 69, 0.6);
    font-size: 1.15rem;
    font-weight: 700;
}

.tm-error {
    padding: 0.6rem 0.8rem;
    border-radius: 0.6rem;
    background: #fbe4e1;
    color: #9b2318;
    font-size: 0.9rem;
}

.tm-done {
    align-items: center;
    gap: 0.6rem;
    padding: 1.6rem 1.4rem 1.4rem;
    max-width: 24rem;
    animation: sheet-up 0.35s ease-out;
}

.tm-done-mark {
    display: flex;
    width: 4rem;
    height: 4rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(47, 125, 79, 0.12);
    color: #2f7d4f;
}

.tm-done-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem 1.2rem;
    border-radius: 0.7rem;
    border: 1px dashed #b8892f;
    background: #f7ecd0;
}

.tm-done-number strong {
    font-size: 1.05rem;
    letter-spacing: 0.04em;
}

.tm-link {
    font-size: 0.9rem;
    text-decoration: underline;
    color: #8a6420;
}
</style>
