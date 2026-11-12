<script setup>
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import LanguageSwitch from '@/components/ui-kit/LanguageSwitch.vue';
import { formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { makeMenuImage } from '@/lib/menuImage';
import { makePreorderImage } from '@/lib/preorderImage';
import { notify } from '@/lib/notify';
import { localized, photoSrc } from '@/lib/site';

/**
 * The menu book: a cover, pages of dishes, a pre-order basket and a share sheet. It adapts to the
 * screen: one narrow page with a bottom bar on phones, one wide page and a side drawer on tablets,
 * a two-page spread with the basket beside it on laptops and desktops.
 */
const props = defineProps({
    branch: { type: Object, required: true },
    categories: { type: Array, required: true },
    // Staff can attach a table; the public link cannot.
    tables: { type: Array, default: () => [] },
    // Staff can note an advance payment; a guest on the public link cannot.
    allowAdvance: { type: Boolean, default: false },
    postUrl: { type: String, required: true },
    shareUrl: { type: String, required: true },
    placed: { type: Object, default: null },
    // Inside the admin layout the book fits the space under the top bar instead of the whole window.
    embedded: { type: Boolean, default: false },
    showLanguage: { type: Boolean, default: false },
    storageKey: { type: String, default: 'dt-preorder-cart' },
});

const emit = defineEmits(['placed']);

const opened = ref(props.placed !== null);
const mode = ref('phone'); // phone | tablet | desktop | wide
const pageIndex = ref(0);
const direction = ref('next');
const contentsOpen = ref(false);
const cartOpen = ref(false);
const shareOpen = ref(false);
const sending = ref(false);
const errors = ref({});
const done = ref(props.placed);
const cart = ref([]);
const selected = ref(null);
const choice = ref({ variantId: null, addOns: {}, quantity: 1 });
const blankGuest = () => ({ name: '', mobile: '', address: '', whenLocal: '', partySize: '', tableId: '', advance: '', note: '' });
const guest = ref(blankGuest());
const shareBusy = ref(false);
// The basket has two steps: check the items, then the optional details.
const step = ref('items');

// ---- screen size -> how many dishes a page holds ----

const perPage = computed(() => ({ phone: 5, tablet: 6, desktop: 5, wide: 5 })[mode.value]);
const spread = computed(() => (mode.value === 'desktop' || mode.value === 'wide' ? 2 : 1));
const basketBeside = computed(() => mode.value === 'wide');

function updateMode() {
    const width = window.innerWidth;
    const next = width >= 1280 ? 'wide' : width >= 1024 ? 'desktop' : width >= 640 ? 'tablet' : 'phone';

    if (next !== mode.value) {
        const firstItem = pages.value[pageIndex.value];
        mode.value = next;

        // Stay on the same dish when the page size changes.
        nextTick(() => {
            const target = firstItem ? pages.value.findIndex((page) => page.chapter === firstItem.chapter) : 0;
            pageIndex.value = Math.floor(Math.max(0, target) / spread.value) * spread.value;
        });
    }
}

// ---- pages: a chapter is a category, split so each page holds a handful of dishes ----

const pages = computed(() => {
    const result = [];

    props.categories.forEach((category, chapter) => {
        const items = category.items ?? [];

        for (let start = 0; start < items.length; start += perPage.value) {
            result.push({ chapter, category, first: start === 0, items: items.slice(start, start + perPage.value) });
        }
    });

    return result;
});

const visiblePages = computed(() => pages.value.slice(pageIndex.value, pageIndex.value + spread.value));
const lastStart = computed(() => Math.max(0, Math.ceil(pages.value.length / spread.value) * spread.value - spread.value));
const currentChapter = computed(() => visiblePages.value[0]?.chapter ?? 0);
const pageLabel = computed(() => {
    const last = Math.min(pages.value.length, pageIndex.value + spread.value);
    const current = spread.value === 2 && last > pageIndex.value + 1 ? `${pageIndex.value + 1}-${last}` : `${pageIndex.value + 1}`;

    return t('preorders.page_of', { current, total: pages.value.length });
});

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
    touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
}

function onTouchEnd(event) {
    if (touchStart === null) {
        return;
    }

    const dx = event.changedTouches[0].clientX - touchStart.x;
    const dy = event.changedTouches[0].clientY - touchStart.y;

    touchStart = null;

    // A mostly horizontal swipe turns the page; a vertical scroll never does.
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        turn(dx < 0 ? 1 : -1);
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        selected.value = null;
        cartOpen.value = false;
        contentsOpen.value = false;
        shareOpen.value = false;

        return;
    }

    if (! opened.value || selected.value || cartOpen.value || contentsOpen.value || shareOpen.value) {
        return;
    }

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
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

function hasOptions(item) {
    return (item.variants ?? []).length > 0 || activeAddOns(item).length > 0;
}

// The plus button adds a plain dish at once; a dish with sizes or extras opens its sheet.
function quickAdd(item) {
    if (hasOptions(item)) {
        openItem(item);

        return;
    }

    const uid = lineKey(item.id, null, []);
    const existing = cart.value.find((line) => line.uid === uid);

    done.value = null;

    if (existing) {
        existing.quantity = Math.min(50, existing.quantity + 1);
    } else {
        cart.value.push({ uid, item_id: item.id, name: item.name, name_bn: item.name_bn, variant_id: null, variant_name: null, variant_name_bn: null, unit: Number(item.base_price), add_ons: [], quantity: 1 });
    }
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
        existing.quantity = Math.min(50, existing.quantity + choice.value.quantity);
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

    done.value = null;
    selected.value = null;
}

// ---- the pre-order ----

const itemCount = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0));
const subtotal = computed(() => cart.value.reduce((sum, line) => sum + line.unit * line.quantity, 0));
const serviceCharge = computed(() => subtotal.value * Number(props.branch.service_charge_percent ?? 0) / 100);
const vatAmount = computed(() => {
    const rate = Number(props.branch.vat_percent ?? 0);
    const base = subtotal.value + serviceCharge.value;

    return props.branch.vat_inclusive ? base - base * 100 / (100 + rate) : base * rate / 100;
});
const total = computed(() => Math.round(props.branch.vat_inclusive ? subtotal.value + serviceCharge.value : subtotal.value + serviceCharge.value + vatAmount.value));

const advanceAmount = computed(() => Math.max(0, Number(guest.value.advance) || 0));
const balanceDue = computed(() => Math.max(0, total.value - advanceAmount.value));

function changeQuantity(line, delta) {
    line.quantity = Math.min(50, line.quantity + delta);

    if (line.quantity <= 0) {
        cart.value = cart.value.filter((entry) => entry.uid !== line.uid);

        if (cart.value.length === 0) {
            step.value = 'items';
        }
    }
}

function firstError() {
    return String(Object.values(errors.value)[0] ?? '');
}

let stepChangedAt = 0;

// "Continue" and "Place pre-order" sit in the same spot, so a double tap on Continue must not
// place the pre-order before the guest has seen the details step.
function submitStep() {
    if (step.value === 'items') {
        step.value = 'details';
        stepChangedAt = Date.now();

        return;
    }

    if (Date.now() - stepChangedAt < 700) {
        return;
    }

    placeOrder();
}

function placeOrder() {
    if (sending.value || cart.value.length === 0) {
        return;
    }

    sending.value = true;
    errors.value = {};

    router.post(props.postUrl, {
        items: cart.value.map((line) => ({
            menu_item_id: line.item_id,
            variant_id: line.variant_id,
            quantity: line.quantity,
            add_ons: line.add_ons.map((addOn) => ({ id: addOn.id, qty: addOn.qty })),
        })),
        customer_name: guest.value.name.trim() || null,
        customer_mobile: guest.value.mobile.trim() || null,
        address: guest.value.address.trim() || null,
        party_size: guest.value.partySize || null,
        expected_at: guest.value.whenLocal ? new Date(guest.value.whenLocal).toISOString() : null,
        restaurant_table_id: guest.value.tableId || null,
        advance_amount: props.allowAdvance && guest.value.advance !== '' ? guest.value.advance : null,
        note: guest.value.note.trim() || null,
    }, {
        preserveScroll: true,
        onError: (received) => {
            errors.value = received;
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

watch(() => props.placed, (placed) => {
    if (placed) {
        done.value = placed;
        step.value = 'items';
        cart.value = [];
        guest.value = blankGuest();
        opened.value = true;
        cartOpen.value = true;
        emit('placed', placed);
    }
});

function startAnother() {
    done.value = null;
    step.value = 'items';
    cartOpen.value = false;
}

// The basket survives a refresh: it is kept on this device, never on the server.
watch(cart, (lines) => {
    try {
        localStorage.setItem(props.storageKey, JSON.stringify(lines));
    } catch {
        // storage unavailable
    }
}, { deep: true });

function lineName(line) {
    return localized(line, 'name');
}

function lineVariant(line) {
    return line.variant_name ? localized({ name: line.variant_name, name_bn: line.variant_name_bn }, 'name') : '';
}

// ---- share ----

const canNativeShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function');

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        notify.success(t('preorders.link_copied'));
    } catch {
        const field = document.querySelector('[data-share-link]');
        field?.select();
    }
}

async function shareLink() {
    try {
        await navigator.share({ title: localized(props.branch, 'name'), url: props.shareUrl });
    } catch {
        // the person closed the share sheet
    }
}

async function shareImage() {
    if (shareBusy.value) {
        return;
    }

    shareBusy.value = true;

    try {
        const blob = await makeMenuImage({
            branchName: localized(props.branch, 'name'),
            tagline: t('preorders.image_tagline'),
            scanText: t('preorders.scan_to_preorder'),
            url: props.shareUrl,
            categories: props.categories,
            name: (record) => localized(record, 'name'),
            price: (item) => `${(item.variants ?? []).length ? `${t('preorders.from')} ` : ''}${formatMoney(startingPrice(item), { decimals: 0 })}`,
        });

        const file = new File([blob], `menu-${String(props.branch.code).toLowerCase()}.png`, { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: localized(props.branch, 'name'), url: props.shareUrl });
            } catch {
                // the person closed the share sheet
            }
        } else {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = file.name;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }
    } catch {
        notify.error(t('preorders.image_failed'));
    } finally {
        shareBusy.value = false;
    }
}

// Share the finished pre-order as a picture: the share sheet (WhatsApp and others) when the device
// supports sharing files, otherwise a download.
const copyBusy = ref(false);

async function shareCopy() {
    const placedOrder = done.value;

    if (copyBusy.value || ! placedOrder) {
        return;
    }

    copyBusy.value = true;

    try {
        const details = [];

        if (placedOrder.customer_name) {
            details.push([t('preorders.slip_guest'), placedOrder.customer_name]);
        }

        if (placedOrder.customer_mobile) {
            details.push([t('preorders.mobile'), placedOrder.customer_mobile]);
        }

        if (placedOrder.address) {
            details.push([t('preorders.address'), placedOrder.address]);
        }

        if (placedOrder.expected_at) {
            details.push([t('preorders.slip_for'), formatDateTime(placedOrder.expected_at)]);
        }

        if (placedOrder.party_size) {
            details.push([t('preorders.party_size'), String(placedOrder.party_size)]);
        }

        if (placedOrder.table) {
            details.push([t('preorders.table'), placedOrder.table]);
        }

        const totals = [{ label: t('preorders.total'), value: formatMoney(placedOrder.total), strong: true }];

        if (Number(placedOrder.advance_amount) > 0) {
            totals.push({ label: t('preorders.advance_received'), value: `- ${formatMoney(placedOrder.advance_amount)}` });
            totals.push({ label: t('preorders.balance_due'), value: formatMoney(placedOrder.balance_due), strong: true });
        }

        const blob = await makePreorderImage({
            branchName: localized(props.branch, 'name'),
            branchLine: [props.branch.phone, localized(props.branch, 'address')].filter(Boolean).join(' - '),
            number: placedOrder.order_no,
            labels: { title: t('preorders.slip_title'), number: t('preorders.order_number') },
            details,
            items: placedOrder.items.map((line) => ({
                name: `${line.quantity} x ${localized(line, 'name')}${line.variant_name ? ` (${localized({ name: line.variant_name, name_bn: line.variant_name_bn }, 'name')})` : ''}`,
                amount: formatMoney(line.line_total),
            })),
            totals,
            note: placedOrder.note ? `${t('preorders.note')}: ${placedOrder.note}` : '',
            footer: t('preorders.slip_note'),
        });

        const file = new File([blob], `${placedOrder.order_no}.png`, { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: `${t('preorders.slip_title')} ${placedOrder.order_no}` });
            } catch {
                // the person closed the share sheet
            }
        } else {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = file.name;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }
    } catch {
        notify.error(t('preorders.image_failed'));
    } finally {
        copyBusy.value = false;
    }
}

// ---- lifecycle ----

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(props.storageKey) ?? '[]');

        if (Array.isArray(saved) && ! props.placed) {
            cart.value = saved;
        }
    } catch {
        // ignore a damaged copy
    }

    mode.value = window.innerWidth >= 1280 ? 'wide' : window.innerWidth >= 1024 ? 'desktop' : window.innerWidth >= 640 ? 'tablet' : 'phone';
    window.addEventListener('resize', updateMode);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateMode);
    window.removeEventListener('keydown', onKeydown);
});

// Keep the tab bar showing the chapter that is open.
watch(currentChapter, () => nextTick(() => document.querySelector('[data-chapter-tab][aria-selected="true"]')?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })));

const basketVisible = computed(() => basketBeside.value || cartOpen.value);
const lockScroll = computed(() => (cartOpen.value && ! basketBeside.value) || selected.value !== null || shareOpen.value || contentsOpen.value);

watch(lockScroll, (locked) => {
    document.body.style.overflow = locked ? 'hidden' : '';
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="pb" :class="[`pb-${mode}`, embedded ? 'pb-embedded' : 'pb-page']">
        <header class="pb-top">
            <div class="min-w-0">
                <p class="pb-brand">{{ localized(branch, 'name') }}</p>
                <p class="pb-brand-sub">{{ t('preorders.menu_word') }} - {{ t('preorders.title') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="pb-pill" @click="shareOpen = true">
                    <AppIcon name="share-nodes" size="13" />
                    <span class="pb-pill-text">{{ t('preorders.share') }}</span>
                </button>
                <LanguageSwitch v-if="showLanguage" />
            </div>
        </header>

        <!-- Nothing to show -->
        <main v-if="categories.length === 0" class="pb-stage">
            <p class="pb-empty">{{ t('preorders.empty_book') }}</p>
        </main>

        <!-- Cover -->
        <main v-else-if="! opened" class="pb-stage">
            <section class="pb-cover" aria-labelledby="pb-cover-title">
                <div class="pb-cover-frame">
                    <span class="pb-emblem"><AppIcon name="utensils" size="26" /></span>
                    <p class="pb-kicker">{{ t('preorders.welcome') }}</p>
                    <h1 id="pb-cover-title" class="pb-cover-title">{{ localized(branch, 'name') }}</h1>
                    <div class="pb-rule" />
                    <p class="pb-menu-word">{{ t('preorders.menu_word') }}</p>
                    <p class="pb-cover-line">{{ t('preorders.welcome_line') }}</p>
                    <button type="button" class="pb-open" @click="opened = true">
                        <AppIcon name="book-open" size="16" />
                        {{ t('preorders.open_book') }}
                    </button>
                </div>
            </section>
        </main>

        <!-- The book -->
        <div v-else class="pb-body">
            <main class="pb-stage pb-stage-book" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
                <nav class="pb-tabs" :aria-label="t('preorders.chapters')">
                    <button
                        v-for="(category, chapter) in categories"
                        :key="category.id"
                        type="button"
                        data-chapter-tab
                        class="pb-tab"
                        :aria-selected="currentChapter === chapter"
                        @click="goToChapter(chapter)"
                    >
                        {{ localized(category, 'name') }}
                    </button>
                </nav>

                <div class="pb-book">
                    <div :key="`${mode}-${pageIndex}-${direction}`" class="pb-spread" :class="`pb-turn-${direction}`">
                        <article v-for="(page, index) in visiblePages" :key="`${page.chapter}-${pageIndex + index}`" class="pb-page-sheet" :class="index === 0 ? 'pb-left' : 'pb-right'">
                            <header class="pb-page-head">
                                <h2 class="pb-chapter" :class="page.first ? '' : 'pb-chapter-cont'">{{ localized(page.category, 'name') }}</h2>
                                <div class="pb-rule pb-rule-dark" />
                            </header>

                            <ul class="pb-list">
                                <li v-for="item in page.items" :key="item.id">
                                    <div class="pb-item" role="button" tabindex="0" @click="openItem(item)" @keydown.enter.self="openItem(item)" @keydown.space.self.prevent="openItem(item)">
                                        <span class="pb-thumb">
                                            <img v-if="itemPhoto(item)" :src="itemPhoto(item)" :alt="localized(item, 'name')" loading="lazy">
                                            <AppIcon v-else name="utensils" size="18" />
                                        </span>
                                        <span class="pb-item-body">
                                            <span class="pb-item-line">
                                                <span class="pb-name">{{ localized(item, 'name') }}</span>
                                                <span class="pb-leader" aria-hidden="true" />
                                                <span class="pb-price">
                                                    <span v-if="(item.variants ?? []).length" class="pb-from">{{ t('preorders.from') }}</span>
                                                    {{ formatMoney(startingPrice(item), { decimals: 0 }) }}
                                                </span>
                                            </span>
                                            <span v-if="localized(item, 'description')" class="pb-desc">{{ localized(item, 'description') }}</span>
                                        </span>
                                        <button type="button" class="pb-add" :class="inCart(item) > 0 ? 'pb-add-on' : ''" :aria-label="`${t('preorders.add')}: ${localized(item, 'name')}`" @click.stop="quickAdd(item)">
                                            <span v-if="inCart(item) > 0" class="tabular-nums">{{ inCart(item) }}</span>
                                            <AppIcon v-else name="plus" size="12" />
                                        </button>
                                    </div>
                                </li>
                            </ul>

                            <footer class="pb-folio">{{ pageIndex + index + 1 }}</footer>
                        </article>
                    </div>
                </div>
            </main>

            <!-- The basket: a bottom sheet on phones, a side drawer on tablets, a column on wide screens -->
            <div v-if="cartOpen && ! basketBeside" class="pb-scrim" @click="cartOpen = false" />
            <aside class="pb-basket" :class="basketVisible ? 'pb-basket-on' : ''" :aria-label="t('preorders.your_order')">
                <header class="pb-sheet-head">
                    <button v-if="! done && cart.length > 0 && step === 'details'" type="button" class="pb-x pb-back" :aria-label="t('preorders.back')" @click="step = 'items'"><AppIcon name="chevron-left" size="14" /></button>
                    <div class="min-w-0 flex-1">
                        <h2 class="pb-chapter">{{ done ? t('preorders.done_title') : (step === 'details' && cart.length > 0 ? t('preorders.details') : t('preorders.your_order')) }}</h2>
                        <p v-if="! done && itemCount > 0" class="pb-sub">{{ t('preorders.items_count', { count: itemCount }) }}</p>
                    </div>
                    <button v-if="! basketBeside" type="button" class="pb-x" :aria-label="t('preorders.close')" @click="cartOpen = false"><AppIcon name="xmark" size="14" /></button>
                </header>

                <!-- Placed -->
                <div v-if="done" class="pb-basket-body pb-done">
                    <span class="pb-done-mark"><AppIcon name="circle-check" size="30" /></span>
                    <p class="pb-sub pb-center">{{ t('preorders.done_line') }}</p>
                    <div class="pb-number">
                        <span class="pb-number-label">{{ t('preorders.order_number') }}</span>
                        <span class="pb-number-value">{{ done.order_no }}</span>
                    </div>
                    <ul class="pb-lines pb-lines-compact">
                        <li v-for="(line, index) in done.items" :key="index" class="pb-line">
                            <span class="pb-line-name">{{ line.quantity }} x {{ localized(line, 'name') }}</span>
                            <span class="tabular-nums">{{ formatMoney(line.line_total, { decimals: 0 }) }}</span>
                        </li>
                    </ul>
                    <p class="pb-total-row"><span>{{ t('preorders.total') }}</span><strong class="tabular-nums">{{ formatMoney(done.total, { decimals: 0 }) }}</strong></p>
                    <template v-if="Number(done.advance_amount) > 0">
                        <p class="pb-total-row pb-total-sub"><span>{{ t('preorders.advance_received') }}</span><span class="tabular-nums">- {{ formatMoney(done.advance_amount, { decimals: 0 }) }}</span></p>
                        <p class="pb-total-row"><span>{{ t('preorders.balance_due') }}</span><strong class="tabular-nums">{{ formatMoney(done.balance_due, { decimals: 0 }) }}</strong></p>
                    </template>
                    <p v-if="done.sms_sent" class="pb-note-ok"><AppIcon name="mobile-screen" size="13" /> {{ t('preorders.sms_sent_line') }}</p>
                    <div class="pb-actions">
                        <a :href="done.print_url" target="_blank" rel="noopener" class="pb-primary pb-primary-link"><AppIcon name="print" size="15" /> {{ t('preorders.print_copy') }}</a>
                        <button type="button" class="pb-primary pb-primary-link pb-share-copy" :disabled="copyBusy" @click="shareCopy"><AppIcon name="share-nodes" size="15" /> {{ copyBusy ? t('preorders.making_image') : t('preorders.share_copy') }}</button>
                        <button type="button" class="pb-ghost pb-actions-wide" @click="startAnother">{{ t('preorders.another') }}</button>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else-if="cart.length === 0" class="pb-basket-body">
                    <p class="pb-empty-cart">{{ t('preorders.cart_empty') }}</p>
                </div>

                <!-- Lines and details -->
                <form v-else class="pb-basket-form" @submit.prevent="submitStep">
                    <div class="pb-basket-body">
                        <template v-if="step === 'items'">
                            <ul class="pb-lines">
                                <li v-for="line in cart" :key="line.uid" class="pb-line pb-line-edit">
                                    <div class="min-w-0 flex-1">
                                        <p class="pb-line-name">{{ lineName(line) }}<span v-if="lineVariant(line)" class="pb-line-variant"> ({{ lineVariant(line) }})</span></p>
                                        <p v-if="line.add_ons.length" class="pb-line-extra">{{ line.add_ons.map((addOn) => `${addOn.qty} x ${localized(addOn, 'name')}`).join(', ') }}</p>
                                        <p class="pb-line-price tabular-nums">{{ formatMoney(line.unit * line.quantity, { decimals: 0 }) }}</p>
                                    </div>
                                    <span class="pb-stepper">
                                        <button type="button" :aria-label="t('preorders.remove')" @click="changeQuantity(line, -1)"><AppIcon name="minus" size="11" /></button>
                                        <span class="tabular-nums">{{ line.quantity }}</span>
                                        <button type="button" :aria-label="t('preorders.add')" @click="changeQuantity(line, 1)"><AppIcon name="plus" size="11" /></button>
                                    </span>
                                </li>
                            </ul>

                            <dl class="pb-totals">
                                <div><dt>{{ t('preorders.subtotal') }}</dt><dd class="tabular-nums">{{ formatMoney(subtotal, { decimals: 0 }) }}</dd></div>
                                <div v-if="serviceCharge > 0"><dt>{{ t('preorders.service') }}</dt><dd class="tabular-nums">{{ formatMoney(serviceCharge, { decimals: 0 }) }}</dd></div>
                                <div v-if="Number(branch.vat_percent) > 0"><dt>{{ t('preorders.vat') }}</dt><dd class="tabular-nums">{{ formatMoney(vatAmount, { decimals: 0 }) }}</dd></div>
                                <div class="pb-grand"><dt>{{ t('preorders.total') }}</dt><dd class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</dd></div>
                            </dl>
                            <p class="pb-hint">{{ t('preorders.estimate_hint') }}</p>
                        </template>

                        <fieldset v-else class="pb-fields">
                            <p class="pb-summary">{{ t('preorders.items_count', { count: itemCount }) }} - <strong class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</strong></p>
                            <p class="pb-hint pb-hint-top">{{ t('preorders.details_hint') }}</p>

                            <label class="pb-field">
                                <span>{{ t('preorders.name') }}</span>
                                <input v-model="guest.name" type="text" maxlength="80" autocomplete="name" enterkeyhint="next" class="pb-input">
                            </label>
                            <label class="pb-field">
                                <span>{{ t('preorders.mobile') }}</span>
                                <input v-model="guest.mobile" type="tel" inputmode="tel" maxlength="20" autocomplete="tel" enterkeyhint="next" class="pb-input" placeholder="01XXXXXXXXX" :aria-invalid="errors.customer_mobile ? 'true' : 'false'">
                                <small v-if="errors.customer_mobile" class="pb-error">{{ errors.customer_mobile }}</small>
                                <small v-else class="pb-hint pb-hint-top">{{ t('preorders.mobile_hint') }}</small>
                            </label>
                            <label class="pb-field">
                                <span>{{ t('preorders.address') }}</span>
                                <input v-model="guest.address" type="text" maxlength="300" autocomplete="street-address" enterkeyhint="next" class="pb-input" :placeholder="t('preorders.address_placeholder')">
                            </label>
                            <div class="pb-pair">
                                <label class="pb-field">
                                    <span>{{ t('preorders.when') }}</span>
                                    <input v-model="guest.whenLocal" type="datetime-local" class="pb-input">
                                </label>
                                <label class="pb-field">
                                    <span>{{ t('preorders.party_size') }}</span>
                                    <input v-model="guest.partySize" type="number" min="1" max="500" inputmode="numeric" class="pb-input">
                                </label>
                            </div>
                            <div v-if="tables.length || allowAdvance" class="pb-pair pb-pair-wide" :class="tables.length && allowAdvance ? '' : 'pb-pair-single'">
                                <label v-if="tables.length" class="pb-field">
                                    <span>{{ t('preorders.table') }}</span>
                                    <select v-model="guest.tableId" class="pb-input">
                                        <option value="">{{ t('preorders.no_table') }}</option>
                                        <option v-for="table in tables" :key="table.id" :value="table.id">{{ table.name }}<template v-if="table.area"> - {{ table.area }}</template></option>
                                    </select>
                                </label>
                                <label v-if="allowAdvance" class="pb-field">
                                    <span>{{ t('preorders.advance') }}</span>
                                    <input v-model="guest.advance" type="number" min="0" step="1" inputmode="decimal" class="pb-input" placeholder="0" :aria-invalid="errors.advance_amount ? 'true' : 'false'">
                                </label>
                            </div>
                            <small v-if="allowAdvance && errors.advance_amount" class="pb-error pb-pair-note">{{ errors.advance_amount }}</small>
                            <small v-else-if="allowAdvance && advanceAmount > 0" class="pb-hint pb-hint-top pb-pair-note">{{ t('preorders.balance_due') }}: <strong class="tabular-nums">{{ formatMoney(balanceDue, { decimals: 0 }) }}</strong></small>
                            <small v-else-if="allowAdvance" class="pb-hint pb-hint-top pb-pair-note">{{ t('preorders.advance_hint') }}</small>
                            <label class="pb-field">
                                <span>{{ t('preorders.note') }}</span>
                                <textarea v-model="guest.note" rows="2" maxlength="300" class="pb-input pb-textarea" :placeholder="t('preorders.note_placeholder')" />
                            </label>

                            <p v-if="firstError() && ! errors.customer_mobile && ! errors.advance_amount" class="pb-error pb-error-box" role="alert">{{ firstError() }}</p>
                        </fieldset>
                    </div>

                    <footer class="pb-sheet-foot">
                        <button v-if="step === 'items'" key="continue" type="submit" class="pb-primary">
                            <span>{{ t('preorders.continue') }}</span>
                            <span class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</span>
                        </button>
                        <button v-else key="place" type="submit" class="pb-primary" :disabled="sending">
                            <span>{{ sending ? t('preorders.sending') : t('preorders.place') }}</span>
                            <span class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</span>
                        </button>
                    </footer>
                </form>
            </aside>

            <!-- Page controls and basket button -->
            <div class="pb-dock">
                <div class="pb-dock-nav">
                    <button type="button" class="pb-round" :disabled="pageIndex === 0" :aria-label="t('preorders.previous')" @click="turn(-1)">
                        <AppIcon name="chevron-left" size="16" />
                    </button>
                    <button type="button" class="pb-pagebtn" @click="contentsOpen = true">
                        <AppIcon name="book-open" size="13" />
                        <span>{{ pageLabel }}</span>
                    </button>
                    <button type="button" class="pb-round" :disabled="pageIndex >= lastStart" :aria-label="t('preorders.next')" @click="turn(1)">
                        <AppIcon name="chevron-right" size="16" />
                    </button>
                </div>

                <button v-if="! basketBeside" type="button" class="pb-cartbtn" :class="itemCount === 0 && ! done ? 'pb-cartbtn-idle' : ''" @click="cartOpen = true">
                    <AppIcon name="bag-shopping" size="16" />
                    <span class="pb-cartbtn-text">{{ itemCount === 0 ? t('preorders.your_order') : t('preorders.view_order') }}</span>
                    <span v-if="itemCount > 0" class="pb-cartbtn-badge">{{ itemCount }}</span>
                    <span v-if="itemCount > 0" class="tabular-nums">{{ formatMoney(total, { decimals: 0 }) }}</span>
                </button>
            </div>
        </div>

        <!-- Contents -->
        <Teleport to="body">
            <div v-if="contentsOpen" class="pb-overlay" @click.self="contentsOpen = false">
                <section class="pb-sheet" role="dialog" aria-modal="true" :aria-label="t('preorders.contents')">
                    <header class="pb-sheet-head">
                        <h2 class="pb-chapter">{{ t('preorders.contents') }}</h2>
                        <button type="button" class="pb-x" :aria-label="t('preorders.close')" @click="contentsOpen = false"><AppIcon name="xmark" size="14" /></button>
                    </header>
                    <ol class="pb-contents">
                        <li v-for="(category, chapter) in categories" :key="category.id">
                            <button type="button" class="pb-contents-row" :class="currentChapter === chapter ? 'pb-contents-current' : ''" @click="goToChapter(chapter)">
                                <span class="pb-contents-name">{{ localized(category, 'name') }}</span>
                                <span class="pb-leader" aria-hidden="true" />
                                <span class="pb-contents-page">{{ pages.findIndex((page) => page.chapter === chapter) + 1 }}</span>
                            </button>
                        </li>
                    </ol>
                </section>
            </div>
        </Teleport>

        <!-- A dish -->
        <Teleport to="body">
            <div v-if="selected" class="pb-overlay" @click.self="selected = null">
                <section class="pb-sheet" role="dialog" aria-modal="true" :aria-label="localized(selected, 'name')">
                    <div v-if="itemPhoto(selected)" class="pb-hero"><img :src="itemPhoto(selected)" :alt="localized(selected, 'name')"></div>
                    <header class="pb-sheet-head">
                        <div class="min-w-0">
                            <h2 class="pb-chapter">{{ localized(selected, 'name') }}</h2>
                            <p v-if="localized(selected, 'description')" class="pb-desc pb-desc-full">{{ localized(selected, 'description') }}</p>
                        </div>
                        <button type="button" class="pb-x" :aria-label="t('preorders.close')" @click="selected = null"><AppIcon name="xmark" size="14" /></button>
                    </header>

                    <div v-if="(selected.variants ?? []).length || activeAddOns(selected).length" class="pb-sheet-body">
                        <div v-if="(selected.variants ?? []).length">
                            <h3 class="pb-label">{{ t('preorders.choose_size') }}</h3>
                            <div class="pb-choices" role="radiogroup">
                                <button
                                    v-for="variant in sortedVariants(selected)"
                                    :key="variant.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="choice.variantId === variant.id"
                                    class="pb-choice"
                                    :class="choice.variantId === variant.id ? 'pb-choice-on' : ''"
                                    @click="choice.variantId = variant.id"
                                >
                                    <span>{{ localized(variant, 'name') }}</span>
                                    <span class="tabular-nums">{{ formatMoney(variant.price, { decimals: 0 }) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="activeAddOns(selected).length">
                            <h3 class="pb-label">{{ t('preorders.add_ons') }}</h3>
                            <ul class="pb-addons">
                                <li v-for="addOn in activeAddOns(selected)" :key="addOn.id" class="pb-addon" :class="(choice.addOns[addOn.id] ?? 0) > 0 ? 'pb-addon-on' : ''">
                                    <span class="min-w-0 flex-1 truncate">{{ localized(addOn, 'name') }}</span>
                                    <span class="pb-addon-price tabular-nums">+ {{ formatMoney(addOn.price, { decimals: 0 }) }}</span>
                                    <span class="pb-stepper">
                                        <button type="button" :disabled="(choice.addOns[addOn.id] ?? 0) === 0" :aria-label="t('preorders.remove')" @click="setAddOn(addOn.id, (choice.addOns[addOn.id] ?? 0) - 1)"><AppIcon name="minus" size="11" /></button>
                                        <span class="tabular-nums">{{ choice.addOns[addOn.id] ?? 0 }}</span>
                                        <button type="button" :aria-label="t('preorders.add')" @click="setAddOn(addOn.id, (choice.addOns[addOn.id] ?? 0) + 1)"><AppIcon name="plus" size="11" /></button>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <footer class="pb-sheet-foot pb-sheet-foot-row">
                        <span class="pb-stepper pb-stepper-big" :aria-label="t('preorders.quantity')">
                            <button type="button" :disabled="choice.quantity <= 1" @click="choice.quantity -= 1"><AppIcon name="minus" size="13" /></button>
                            <span class="tabular-nums">{{ choice.quantity }}</span>
                            <button type="button" :disabled="choice.quantity >= 50" @click="choice.quantity += 1"><AppIcon name="plus" size="13" /></button>
                        </span>
                        <button type="button" class="pb-primary" @click="addToCart">
                            <span>{{ t('preorders.add_to_order') }}</span>
                            <span class="tabular-nums">{{ formatMoney(choiceUnit * choice.quantity, { decimals: 0 }) }}</span>
                        </button>
                    </footer>
                </section>
            </div>
        </Teleport>

        <!-- Share -->
        <Teleport to="body">
            <div v-if="shareOpen" class="pb-overlay" @click.self="shareOpen = false">
                <section class="pb-sheet" role="dialog" aria-modal="true" :aria-label="t('preorders.share_title')">
                    <header class="pb-sheet-head">
                        <div class="min-w-0">
                            <h2 class="pb-chapter">{{ t('preorders.share_title') }}</h2>
                            <p class="pb-sub">{{ t('preorders.share_hint') }}</p>
                        </div>
                        <button type="button" class="pb-x" :aria-label="t('preorders.close')" @click="shareOpen = false"><AppIcon name="xmark" size="14" /></button>
                    </header>

                    <div class="pb-sheet-body">
                        <label class="pb-field">
                            <span>{{ t('preorders.share_link') }}</span>
                            <span class="pb-copyrow">
                                <input data-share-link type="text" readonly :value="shareUrl" class="pb-input" @focus="$event.target.select()">
                                <button type="button" class="pb-ghost pb-ghost-square" :aria-label="t('preorders.copy_link')" @click="copyLink"><AppIcon name="copy" size="14" /></button>
                            </span>
                        </label>

                        <div class="pb-share-grid">
                            <button type="button" class="pb-share-btn" @click="canNativeShare ? shareLink() : copyLink()">
                                <AppIcon name="link" size="18" />
                                <span>{{ canNativeShare ? t('preorders.share_link_button') : t('preorders.copy_link') }}</span>
                            </button>
                            <button type="button" class="pb-share-btn" :disabled="shareBusy" @click="shareImage">
                                <AppIcon name="image" size="18" />
                                <span>{{ shareBusy ? t('preorders.making_image') : (canNativeShare ? t('preorders.share_image') : t('preorders.save_image')) }}</span>
                            </button>
                        </div>

                        <a :href="shareUrl" target="_blank" rel="noopener" class="pb-link">
                            <AppIcon name="arrow-up-right" size="12" /> {{ t('preorders.open_public') }}
                        </a>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.pb,
.pb-overlay {
    --paper: #fbf6ea;
    --paper-edge: #e4d6b3;
    --ink: #2b2118;
    --ink-soft: #6d5b45;
    --gold: #b8892f;
    --gold-light: #d9b25a;
    --leather: #2c1810;
    --leather-light: #3b2418;
    --green: #2f7d4f;
    --red: #b4372a;
    --gutter: 0.75rem;
    --thumb: 4rem;
}

.pb {
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(ellipse at 50% 0%, rgba(184, 137, 47, 0.18), transparent 60%),
        linear-gradient(180deg, var(--leather-light), var(--leather));
    color: var(--ink);
    font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif;
    -webkit-tap-highlight-color: transparent;
}

.pb-page { min-height: 100vh; min-height: 100dvh; }
.pb-embedded { min-height: calc(100dvh - 5.5rem); border-radius: 0.75rem; overflow: clip; position: relative; }

.pb button, .pb input, .pb select, .pb textarea, .pb-overlay button, .pb-overlay input, .pb-overlay select, .pb-overlay textarea { font-family: inherit; }
.pb h1, .pb h2, .pb h3, .pb-overlay h2, .pb-overlay h3 { font-family: Georgia, 'Noto Serif Bengali', 'Times New Roman', serif; margin: 0; }

.pb-tablet { --gutter: 1.25rem; --thumb: 4.75rem; }
.pb-desktop { --gutter: 1.5rem; --thumb: 4.5rem; }
.pb-wide { --gutter: 1.75rem; --thumb: 5rem; }

/* ---- top bar ---- */
.pb-top {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    padding: 0.7rem var(--gutter);
    border-bottom: 1px solid rgba(184, 137, 47, 0.35);
}
.pb-brand { margin: 0; font-size: 0.9rem; font-weight: 600; letter-spacing: 0.02em; color: #f4ead2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pb-brand-sub { margin: 0.1rem 0 0; font-size: 0.7rem; color: var(--gold-light); letter-spacing: 0.04em; }
.pb-pill {
    display: inline-flex; align-items: center; gap: 0.4rem; height: 2.25rem; padding: 0 0.85rem;
    border: 1px solid var(--gold); border-radius: 999px; background: var(--leather); color: #f4ead2;
    font-size: 0.78rem; font-weight: 600; cursor: pointer;
}
.pb-pill:hover { background: #4a2d1c; }
@media (max-width: 479px) { .pb-pill { width: 2.25rem; padding: 0; justify-content: center; } .pb-pill-text { display: none; } }

/* ---- stage ---- */
.pb-stage { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 1rem var(--gutter) 6.5rem; min-width: 0; }
.pb-stage-book { padding-top: 0.5rem; }
.pb-empty { margin: auto; max-width: 22rem; text-align: center; color: #f4ead2; line-height: 1.6; }

/* ---- cover ---- */
.pb-cover { width: 100%; max-width: 26rem; margin: auto; }
.pb-tablet .pb-cover, .pb-desktop .pb-cover, .pb-wide .pb-cover { max-width: 30rem; }
.pb-cover-frame {
    position: relative; display: flex; flex-direction: column; align-items: center; text-align: center;
    padding: 2.5rem 1.5rem 2rem; border: 2px solid var(--gold); border-radius: 0.75rem;
    background: linear-gradient(180deg, #34200f, #2a180d); box-shadow: 0 0 0 6px rgba(44, 24, 16, 0.9), 0 0 0 7px rgba(184, 137, 47, 0.5), 0 24px 48px rgba(0, 0, 0, 0.45);
}
.pb-tablet .pb-cover-frame, .pb-desktop .pb-cover-frame, .pb-wide .pb-cover-frame { padding: 3.25rem 2.5rem 2.75rem; }
.pb-emblem { display: inline-flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border: 1px solid var(--gold); border-radius: 999px; color: var(--gold-light); }
.pb-kicker { margin: 1.25rem 0 0; font-size: 0.72rem; letter-spacing: 0.3em; text-transform: uppercase; color: var(--gold-light); }
.pb-cover-title { margin-top: 0.6rem; font-size: 1.9rem; line-height: 1.15; color: #f4ead2; overflow-wrap: anywhere; }
.pb-tablet .pb-cover-title, .pb-desktop .pb-cover-title, .pb-wide .pb-cover-title { font-size: 2.4rem; }
.pb-rule { width: 4.5rem; height: 2px; margin: 1.1rem auto; background: var(--gold-light); }
.pb-rule-dark { width: 100%; height: 1px; margin: 0.45rem 0 0; background: linear-gradient(90deg, var(--gold), transparent); }
.pb-menu-word { margin: 0; font-size: 1.35rem; font-style: italic; color: #f4ead2; }
.pb-cover-line { max-width: 20rem; margin: 0.9rem 0 0; font-size: 0.9rem; line-height: 1.55; color: #d9c9a3; }
.pb-open {
    display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.75rem; min-height: 2.9rem; padding: 0 1.6rem;
    border: 1px solid var(--gold-light); border-radius: 999px; background: var(--gold); color: #1d1208; font-size: 0.95rem; font-weight: 700; cursor: pointer;
}
.pb-open:hover { background: var(--gold-light); }

/* ---- layout around the book ---- */
.pb-body { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.pb-wide .pb-body { display: grid; grid-template-columns: minmax(0, 1fr) 23rem; align-items: start; }
.pb-wide .pb-stage { grid-column: 1; grid-row: 1; }
.pb-wide .pb-dock { grid-column: 1; grid-row: 2; }

/* ---- chapter tabs ---- */
.pb-tabs {
    position: sticky; top: 0; z-index: 5; display: flex; gap: 0.4rem; width: 100%; max-width: 64rem; padding: 0.55rem 0;
    overflow-x: auto; scrollbar-width: none; background: linear-gradient(180deg, var(--leather-light) 70%, transparent);
}
.pb-tabs::-webkit-scrollbar { display: none; }
.pb-tab {
    flex: none; min-height: 2.25rem; padding: 0 0.95rem; border: 1px solid rgba(184, 137, 47, 0.5); border-radius: 999px;
    background: transparent; color: #e9dcc0; font-size: 0.82rem; cursor: pointer; white-space: nowrap;
}
.pb-tab[aria-selected='true'] { background: var(--gold); border-color: var(--gold-light); color: #1d1208; font-weight: 700; }

/* ---- the book ---- */
.pb-book { width: 100%; max-width: 40rem; margin-top: 0.25rem; }
.pb-desktop .pb-book, .pb-wide .pb-book { max-width: 62rem; }
.pb-spread { display: grid; grid-template-columns: minmax(0, 1fr); animation-duration: 0.28s; animation-fill-mode: both; }
.pb-desktop .pb-spread, .pb-wide .pb-spread {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); border-radius: 0.6rem; box-shadow: 0 22px 44px rgba(0, 0, 0, 0.4);
}
.pb-turn-next { animation-name: pb-in-next; }
.pb-turn-prev { animation-name: pb-in-prev; }
@keyframes pb-in-next { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: none; } }
@keyframes pb-in-prev { from { opacity: 0; transform: translateX(-14px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .pb-spread { animation: none; } }

.pb-page-sheet {
    position: relative; display: flex; flex-direction: column; min-height: 27rem; padding: 1.25rem 1.1rem 2.75rem;
    background: var(--paper); border: 1px solid var(--paper-edge); border-radius: 0.6rem;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.35);
}
.pb-phone .pb-page-sheet { min-height: max(27rem, calc(100dvh - 15.5rem)); }
.pb-embedded.pb-tablet .pb-page-sheet, .pb-embedded.pb-desktop .pb-page-sheet, .pb-embedded.pb-wide .pb-page-sheet { min-height: max(30rem, calc(100dvh - 21rem)); }
.pb-embedded.pb-phone .pb-page-sheet { min-height: max(27rem, calc(100dvh - 21rem)); }
.pb-tablet .pb-page-sheet { min-height: max(34rem, calc(100dvh - 15rem)); padding: 1.75rem 1.75rem 3rem; }
.pb-desktop .pb-page-sheet, .pb-wide .pb-page-sheet { min-height: max(33rem, calc(100dvh - 15rem)); padding: 1.75rem 1.6rem 3rem; border-radius: 0; box-shadow: none; }
.pb-desktop .pb-left, .pb-wide .pb-left { border-radius: 0.6rem 0 0 0.6rem; background: linear-gradient(90deg, var(--paper) 90%, #efe4c8); }
.pb-desktop .pb-right, .pb-wide .pb-right { border-radius: 0 0.6rem 0.6rem 0; border-left: 0; background: linear-gradient(270deg, var(--paper) 90%, #efe4c8); }
.pb-chapter { font-size: 1.3rem; line-height: 1.2; color: var(--ink); }
.pb-tablet .pb-chapter, .pb-desktop .pb-chapter, .pb-wide .pb-chapter { font-size: 1.5rem; }
.pb-chapter-cont { color: var(--ink-soft); font-style: italic; }
.pb-page-head { margin-bottom: 0.6rem; }

.pb-list { flex: 1; display: flex; flex-direction: column; gap: 0.15rem; margin: 0; padding: 0; list-style: none; }
.pb-item {
    position: relative; display: flex; align-items: center; gap: 0.8rem; width: 100%; min-height: 4.5rem; box-sizing: border-box; padding: 0.55rem 0.4rem;
    border: 0; border-bottom: 1px dotted var(--paper-edge); border-radius: 0.5rem; background: transparent; color: inherit; text-align: left; cursor: pointer;
}
.pb-item:hover { background: rgba(184, 137, 47, 0.08); }
.pb-item:focus-visible, .pb-tab:focus-visible, .pb-round:focus-visible, .pb-pagebtn:focus-visible, .pb-cartbtn:focus-visible, .pb-pill:focus-visible, .pb-open:focus-visible, .pb-primary:focus-visible, .pb-choice:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
.pb-thumb {
    flex: none; display: flex; align-items: center; justify-content: center; width: var(--thumb); height: var(--thumb); overflow: hidden;
    border: 1px solid var(--paper-edge); border-radius: 0.5rem; background: #f1e7cb; color: var(--gold);
}
.pb-thumb img { width: 100%; height: 100%; object-fit: cover; }
.pb-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.2rem; }
.pb-item-line { display: flex; align-items: baseline; gap: 0.4rem; min-width: 0; }
.pb-name { min-width: 0; font-size: 1rem; font-weight: 600; line-height: 1.25; overflow-wrap: anywhere; }
.pb-leader { flex: 1; min-width: 0.5rem; border-bottom: 2px dotted #cdb98c; transform: translateY(-0.25rem); }
.pb-price { flex: none; font-size: 0.98rem; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.pb-from { margin-right: 0.2rem; font-size: 0.68rem; font-weight: 400; color: var(--ink-soft); }
.pb-desc { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.8rem; line-height: 1.4; color: var(--ink-soft); }
.pb-desc-full { display: block; margin-top: 0.3rem; -webkit-line-clamp: unset; }
.pb-add {
    flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border: 1px solid var(--gold); border-radius: 999px;
    background: transparent; color: var(--gold); font-size: 0.85rem; font-weight: 700; cursor: pointer;
}
.pb-add:hover { background: rgba(184, 137, 47, 0.14); }
.pb-add-on { background: var(--green); border-color: var(--green); color: #fff; }
.pb-folio { position: absolute; inset: auto 0 0.85rem; text-align: center; font-size: 0.78rem; font-style: italic; color: var(--ink-soft); }

/* ---- dock ---- */
.pb-dock {
    position: fixed; z-index: 20; inset: auto 0 0; display: flex; align-items: center; justify-content: space-between; gap: 0.6rem;
    padding: 0.65rem var(--gutter) calc(0.65rem + env(safe-area-inset-bottom)); background: rgba(34, 19, 11, 0.96); border-top: 1px solid rgba(184, 137, 47, 0.45);
    backdrop-filter: blur(6px);
}
.pb-embedded .pb-dock { position: sticky; }
.pb-wide .pb-dock { position: sticky; justify-content: center; }
.pb-dock-nav { display: flex; align-items: center; gap: 0.4rem; min-width: 0; }
.pb-round {
    flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border: 1px solid var(--gold); border-radius: 999px;
    background: transparent; color: #f4ead2; cursor: pointer;
}
.pb-round:disabled { opacity: 0.35; cursor: default; }
.pb-pagebtn {
    display: inline-flex; align-items: center; gap: 0.4rem; min-height: 2.75rem; padding: 0 0.85rem; border: 1px solid rgba(184, 137, 47, 0.5); border-radius: 999px;
    background: transparent; color: #e9dcc0; font-size: 0.78rem; cursor: pointer; white-space: nowrap;
}
.pb-cartbtn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; min-height: 2.75rem; padding: 0 1.05rem; border: 0; border-radius: 999px;
    background: var(--gold); color: #1d1208; font-size: 0.9rem; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.pb-cartbtn-idle { background: transparent; border: 1px solid var(--gold); color: #f4ead2; font-weight: 600; }
.pb-cartbtn-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 1.4rem; height: 1.4rem; padding: 0 0.3rem; border-radius: 999px; background: var(--leather); color: #f4ead2; font-size: 0.75rem; }
@media (max-width: 400px) { .pb-pagebtn span { display: none; } .pb-cartbtn-text { display: none; } }

/* ---- basket: sheet on phones, drawer on tablets, column on wide screens ---- */
.pb-scrim { position: fixed; inset: 0; z-index: 40; background: rgba(15, 8, 3, 0.6); }
.pb-basket {
    position: fixed; z-index: 41; inset: auto 0 0; display: none; flex-direction: column; max-height: 92dvh;
    background: var(--paper); border-radius: 1.1rem 1.1rem 0 0; box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.45);
}
.pb-basket-on { display: flex; }
.pb-tablet .pb-basket, .pb-desktop .pb-basket { inset: 0 0 0 auto; width: 26rem; max-width: 100%; max-height: none; border-radius: 1.1rem 0 0 1.1rem; }
.pb-wide .pb-basket {
    position: sticky; top: 0.75rem; z-index: 1; display: flex; grid-column: 2; grid-row: 1 / span 2; width: auto; max-height: calc(100dvh - 1.5rem);
    margin: 1rem 1.75rem 1rem 0; border-radius: 0.75rem; border: 1px solid var(--paper-edge);
}
.pb-embedded.pb-wide .pb-basket { max-height: calc(100dvh - 7rem); }
.pb-basket-form { display: flex; flex-direction: column; min-height: 0; flex: 1; }
.pb-basket-body { flex: 1; min-height: 0; overflow-y: auto; padding: 0.25rem 1.1rem 1rem; overscroll-behavior: contain; }
.pb-empty-cart { margin: 1.5rem 0; font-size: 0.92rem; line-height: 1.55; text-align: center; color: var(--ink-soft); }

/* ---- sheets ---- */
.pb-overlay { position: fixed; inset: 0; z-index: 50; display: flex; align-items: flex-end; justify-content: center; background: rgba(15, 8, 3, 0.65); font-family: Georgia, 'Noto Serif Bengali', serif; color: var(--ink); }
.pb-sheet {
    display: flex; flex-direction: column; width: 100%; max-width: 32rem; max-height: 92dvh; overflow: hidden; background: var(--paper);
    border-radius: 1.1rem 1.1rem 0 0; box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.45);
}
@media (min-width: 640px) {
    .pb-overlay { align-items: center; padding: 1.5rem; }
    .pb-sheet { border-radius: 1rem; max-height: 88dvh; }
}
.pb-sheet-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; padding: 1rem 1.1rem 0.6rem; }
.pb-sheet-body { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; padding: 0.25rem 1.1rem 1rem; }
.pb-sheet-foot { padding: 0.75rem 1.1rem calc(0.85rem + env(safe-area-inset-bottom)); border-top: 1px solid var(--paper-edge); background: #f5edd7; }
.pb-sheet-foot-row { display: flex; align-items: center; gap: 0.75rem; }
.pb-sheet-foot-row .pb-primary { flex: 1; }
.pb-hero { height: 11rem; overflow: hidden; background: #f1e7cb; }
.pb-hero img { width: 100%; height: 100%; object-fit: cover; }
.pb-x { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: 1px solid var(--paper-edge); border-radius: 999px; background: transparent; color: var(--ink); cursor: pointer; }
.pb-back { margin-right: 0.1rem; }
.pb-summary { margin: 0.5rem 0 0; padding: 0.6rem 0.8rem; border: 1px dashed var(--gold); border-radius: 0.6rem; background: #fff7e0; font-size: 0.9rem; }
.pb-hint-top { margin-top: 0; }
.pb-sub { margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--ink-soft); }
.pb-center { text-align: center; }
.pb-label { margin: 0 0 0.45rem; font-size: 0.74rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-soft); }
.pb-hint { margin: 0.3rem 0 0; font-size: 0.74rem; line-height: 1.45; color: var(--ink-soft); }

.pb-choices { display: grid; gap: 0.45rem; }
.pb-choice { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; min-height: 2.75rem; padding: 0 0.9rem; border: 1px solid var(--paper-edge); border-radius: 0.6rem; background: #fff; color: var(--ink); font-size: 0.92rem; cursor: pointer; }
.pb-choice-on { border-color: var(--gold); background: #fff7e0; box-shadow: inset 0 0 0 1px var(--gold); font-weight: 700; }
.pb-addons { margin: 0; padding: 0; list-style: none; display: grid; gap: 0.45rem; }
.pb-addon { display: flex; align-items: center; gap: 0.6rem; min-height: 2.75rem; padding: 0.3rem 0.6rem 0.3rem 0.9rem; border: 1px solid var(--paper-edge); border-radius: 0.6rem; background: #fff; font-size: 0.9rem; }
.pb-addon-on { border-color: var(--gold); background: #fff7e0; }
.pb-addon-price { flex: none; font-size: 0.82rem; color: var(--ink-soft); }

.pb-stepper { flex: none; display: inline-flex; align-items: center; gap: 0.15rem; border: 1px solid var(--paper-edge); border-radius: 999px; background: #fff; }
.pb-stepper > span { min-width: 1.5rem; text-align: center; font-size: 0.9rem; font-weight: 700; }
.pb-stepper button { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border: 0; border-radius: 999px; background: transparent; color: var(--ink); cursor: pointer; }
.pb-stepper button:disabled { opacity: 0.3; cursor: default; }
.pb-stepper-big button { width: 2.75rem; height: 2.75rem; }

.pb-primary {
    display: inline-flex; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%; min-height: 3rem; padding: 0 1.2rem; border: 0; border-radius: 0.75rem;
    background: var(--leather); color: #f4ead2; font-size: 0.95rem; font-weight: 700; cursor: pointer;
}
.pb-primary:hover { background: #4a2d1c; }
.pb-primary:disabled { opacity: 0.6; cursor: wait; }
.pb-primary-link { justify-content: center; text-decoration: none; }
.pb-ghost { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; min-height: 2.75rem; padding: 0 1rem; border: 1px solid var(--paper-edge); border-radius: 0.75rem; background: #fff; color: var(--ink); font-size: 0.9rem; cursor: pointer; }
.pb-ghost-square { flex: none; width: 2.75rem; padding: 0; }

/* ---- lines, totals, form ---- */
.pb-lines { margin: 0; padding: 0; list-style: none; }
.pb-line { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.7rem 0; border-bottom: 1px dotted var(--paper-edge); font-size: 0.9rem; }
.pb-lines-compact .pb-line { padding: 0.4rem 0; }
.pb-line-name { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
.pb-line-variant, .pb-line-extra { font-weight: 400; font-size: 0.78rem; color: var(--ink-soft); margin: 0.1rem 0 0; }
.pb-line-price { margin: 0.2rem 0 0; font-size: 0.85rem; font-weight: 700; }
.pb-totals { margin: 0.75rem 0 0; display: grid; gap: 0.3rem; font-size: 0.88rem; }
.pb-totals > div { display: flex; justify-content: space-between; gap: 1rem; }
.pb-totals dt, .pb-totals dd { margin: 0; }
.pb-grand { padding-top: 0.45rem; border-top: 1px solid var(--paper-edge); font-size: 1.05rem; font-weight: 700; }
.pb-total-row { display: flex; justify-content: space-between; gap: 1rem; margin: 0.6rem 0 0; font-size: 1.05rem; }
.pb-total-sub { margin-top: 0.25rem; font-size: 0.88rem; color: var(--ink-soft); }
.pb-fields { margin: 1rem 0 0; padding: 0; border: 0; display: grid; gap: 0.75rem; min-width: 0; }
.pb-fields legend { padding: 0; }
.pb-field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; font-size: 0.8rem; font-weight: 600; color: var(--ink); }
.pb-pair { display: grid; grid-template-columns: minmax(0, 1fr) 6rem; align-items: end; gap: 0.6rem; }
.pb-pair-wide { grid-template-columns: minmax(0, 1fr) 7rem; }
.pb-pair-single { grid-template-columns: minmax(0, 1fr); }
.pb-pair-single .pb-field:only-child { max-width: 11rem; }
.pb-pair-note { margin-top: -0.35rem; }
.pb-pair .pb-field > span:first-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pb-input {
    width: 100%; min-height: 2.75rem; padding: 0.55rem 0.75rem; border: 1px solid var(--paper-edge); border-radius: 0.6rem; background: #fff; color: var(--ink);
    font-size: 1rem; font-weight: 400; box-sizing: border-box;
}
.pb-input:focus { outline: 2px solid var(--gold); outline-offset: 0; border-color: var(--gold); }
.pb-textarea { resize: vertical; min-height: 4.5rem; line-height: 1.45; }
.pb-copyrow { display: flex; gap: 0.5rem; }
.pb-error { margin: 0; font-size: 0.78rem; font-weight: 400; color: var(--red); }
.pb-error-box { margin-top: 0.75rem; padding: 0.6rem 0.75rem; border: 1px solid #e8b8b1; border-radius: 0.6rem; background: #fdeeec; }
.pb-note-ok { display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin: 0.75rem 0 0; font-size: 0.82rem; color: var(--green); }

/* ---- done ---- */
.pb-done { display: flex; flex-direction: column; gap: 0.5rem; }
.pb-done-mark { align-self: center; margin-top: 0.4rem; color: var(--green); }
.pb-number { display: flex; flex-direction: column; align-items: center; gap: 0.15rem; margin: 0.4rem 0; padding: 0.8rem; border: 1px dashed var(--gold); border-radius: 0.75rem; background: #fff7e0; }
.pb-number-label { font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-soft); }
.pb-number-value { font-size: 1.3rem; font-weight: 700; letter-spacing: 0.06em; font-variant-numeric: tabular-nums; }
.pb-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; margin-top: 0.85rem; }
.pb-actions-wide { grid-column: 1 / -1; }
.pb-share-copy { background: #fff; color: var(--ink); border: 1px solid var(--gold); }
.pb-share-copy:hover { background: #fff7e0; }
.pb-actions .pb-primary { padding: 0 0.75rem; white-space: nowrap; }

/* ---- contents ---- */
.pb-contents { margin: 0; padding: 0 1.1rem 1.25rem; list-style: none; overflow-y: auto; }
.pb-contents-row { display: flex; align-items: baseline; gap: 0.5rem; width: 100%; min-height: 2.75rem; padding: 0.5rem 0.4rem; border: 0; border-radius: 0.5rem; background: transparent; color: inherit; font-size: 1rem; text-align: left; cursor: pointer; }
.pb-contents-current { background: rgba(184, 137, 47, 0.14); font-weight: 700; }
.pb-contents-name { overflow-wrap: anywhere; }
.pb-contents-page { font-variant-numeric: tabular-nums; }

/* ---- share ---- */
.pb-share-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem; }
.pb-share-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; min-height: 5rem; padding: 0.75rem; border: 1px solid var(--paper-edge); border-radius: 0.75rem; background: #fff; color: var(--ink); font-size: 0.85rem; font-weight: 600; text-align: center; cursor: pointer; }
.pb-share-btn:hover { border-color: var(--gold); background: #fff7e0; }
.pb-share-btn:disabled { opacity: 0.6; cursor: wait; }
.pb-link { display: inline-flex; align-items: center; gap: 0.35rem; align-self: center; font-size: 0.82rem; color: var(--ink-soft); }
</style>
