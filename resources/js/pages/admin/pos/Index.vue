<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishArt from '@/components/site/DishArt.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PosTableTile from '@/components/admin/PosTableTile.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import { useConfirm, useConfirmState } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { dishKind, localized } from '@/lib/site';
import { cacheBootstrap, findOutbox, getCachedMenu, getDataVersion, getLocalShift, nextLocalInvoice, queueOutbox, rememberInvoiceCursor, rememberLocalShift, updateOutboxPayload } from '@/lib/offline';
import { useIncomingOrders } from '@/lib/incomingOrders';
import { notify } from '@/lib/notify';
import { parseInvoiceSequence } from '@/lib/invoice';
import { printHtml, printSequence, printUrl } from '@/lib/print';
import { localKitchenTokenHtml, localReceiptHtml } from '@/lib/receipt';

const props = defineProps({
    categories: { type: Array, required: true },
    tables: { type: Array, default: () => [] },
    openOrders: { type: Array, default: () => [] },
    branch: { type: Object, required: true },
    openShift: { type: Object, default: null },
    canOverrideDiscount: { type: Boolean, default: false },
    canVoid: { type: Boolean, default: false },
    posPinMinutes: { type: Number, default: 5 },
});

const confirm = useConfirm();
const confirmState = useConfirmState().state;
const page = usePage();

const categories = ref(props.categories);
const tables = ref(props.tables);
const branch = ref(props.branch);
const localShift = ref(null);
const openShift = computed(() => props.openShift || localShift.value);

const TICKET_KEY = 'pos-open-ticket';

function readTicket() {
    try {
        return JSON.parse(sessionStorage.getItem(TICKET_KEY) ?? 'null');
    } catch {
        return null;
    }
}

const savedTicket = readTicket();

const activeCategory = ref(null);
const search = ref('');
const orderType = ref(savedTicket?.orderType ?? 'dine_in');
const tableId = ref(savedTicket?.tableId ?? null);
const failedPhotoIds = ref({});
const customerMobile = ref(savedTicket?.customerMobile ?? '');
const cart = ref(savedTicket?.cart ?? []);
const discount = ref(savedTicket?.discount ?? { type: 'percent', value: 0 });
const itemEditor = ref(null);
const variantPicker = ref(null);
const optionsDialog = ref(null);
const heldOrders = ref([]);

// Payment methods this branch accepts, in the order the admin chose
const FALLBACK_METHODS = [
    { method: 'cash', name: 'Cash', name_bn: 'নগদ', type: 'cash', enabled: true },
    { method: 'bkash', name: 'bKash', name_bn: 'বিকাশ', type: 'wallet', enabled: true },
    { method: 'nagad', name: 'Nagad', name_bn: 'নগদ (মোবাইল)', type: 'wallet', enabled: true },
    { method: 'card', name: 'Card / SSLCommerz', name_bn: 'কার্ড / এসএসএলকমার্জ', type: 'card', enabled: true },
];
const payMethodList = computed(() => {
    const enabled = (branch.value.pos_settings?.payment_methods ?? FALLBACK_METHODS).filter((entry) => entry.enabled);

    return enabled.length > 0 ? enabled : [FALLBACK_METHODS[0]];
});
const payMethods = computed(() => payMethodList.value.map((entry) => entry.method));
const cashSuggestions = computed(() => branch.value.pos_settings?.cash_suggestions ?? [1000, 500, 200, 100]);

// Quick cash amounts sit on the payment row itself. When the row is narrow the smallest ones go first.
const chipsWidth = ref(240);
let chipsElement = null;
let chipsObserver = null;

function bindChips(element) {
    if (element === chipsElement) {
        return;
    }

    chipsObserver?.disconnect();
    chipsElement = element;

    if (element && typeof ResizeObserver !== 'undefined') {
        chipsObserver = new ResizeObserver(([entry]) => {
            chipsWidth.value = entry.contentRect.width;
        });
        chipsObserver.observe(element);
    }
}

const cashChips = computed(() => {
    const all = [...new Set([orderDue(), ...cashSuggestions.value])];
    const fits = Math.max(1, Math.floor((chipsWidth.value + 4) / 46));

    return all.slice(0, fits);
});

onBeforeUnmount(() => chipsObserver?.disconnect());

function methodType(code) {
    return payMethodList.value.find((entry) => entry.method === code)?.type ?? (code === 'cash' ? 'cash' : 'other');
}
const blankPayLine = (method = null) => ({ method: method ?? payMethods.value[0], amount: '', reference: '' });
// One row per payment: the first row is the main payment, more rows (the + button) make a split.
const payLines = ref(savedTicket?.payLines ?? [{ ...blankPayLine(), amount: savedTicket?.tendered ?? '' }]);
const tendered = computed({
    get: () => payLines.value[0].amount,
    set: (value) => { payLines.value[0].amount = value; },
});
const payMethod = computed({
    get: () => payLines.value[0].method,
    set: (value) => { payLines.value[0].method = value; },
});
const walletTrxId = computed({
    get: () => payLines.value[0].reference,
    set: (value) => { payLines.value[0].reference = value; },
});
const payMode = computed(() => {
    if (payLines.value.length > 1) {
        return 'split';
    }

    return { cash: 'cash', wallet: 'wallet' }[methodType(payMethod.value)] ?? 'single';
});
const shiftDialogOpen = ref(false);
const tablePickerOpen = ref(false);
const runningOpen = ref(false);
const editingOrder = ref(null);
const openingCash = ref('0.00');
const locked = ref(false);
const pin = ref('');
const pinError = ref('');
let idleTimer = null;
const lastPayment = ref(savedTicket?.lastPayment ?? null);

const TERMINAL = localStorage.getItem('pos-terminal') ?? 'T01';
const latestOrder = ref(savedTicket?.latestOrder ?? null);

watch(
    [cart, discount, orderType, tableId, customerMobile, payLines, latestOrder, lastPayment],
    () => {
        if (cart.value.length === 0 && ! latestOrder.value) {
            sessionStorage.removeItem(TICKET_KEY);

            return;
        }

        sessionStorage.setItem(TICKET_KEY, JSON.stringify({
            cart: cart.value,
            discount: discount.value,
            orderType: orderType.value,
            tableId: tableId.value,
            customerMobile: customerMobile.value,
            payLines: payLines.value,
            latestOrder: latestOrder.value,
            lastPayment: lastPayment.value,
        }));
    },
    { deep: true },
);

const allItems = computed(() => categories.value.flatMap((category) => category.items));

function catalogItem(itemId) {
    return allItems.value.find((item) => item.id === itemId) ?? null;
}

// Variant rows are replaced when the menu is reseeded or edited, so a ticket
// restored from this browser can still hold an id that no longer exists.
function resolveVariant(item, variant) {
    const variants = item?.variants ?? [];

    if (! variant || variants.length === 0) {
        return null;
    }

    return variants.find((entry) => entry.id === variant.id)
        ?? variants.find((entry) => entry.name === variant.name)
        ?? variants.find((entry) => entry.is_default)
        ?? variants[0]
        ?? null;
}

function reconcileCart() {
    cart.value = cart.value.flatMap((line) => {
        const item = catalogItem(line.item?.id);

        if (! item) {
            return [];
        }

        return [{
            ...line,
            item,
            variant: resolveVariant(item, line.variant),
            addOns: (line.addOns ?? []).flatMap((addOn) => {
                const current = activeAddOns(item).find((entry) => entry.id === addOn.id);

                return current ? [{ id: current.id, name: current.name, name_bn: current.name_bn, price: Number(current.price), qty: addOn.qty }] : [];
            }),
        }];
    });
}

reconcileCart();

const filteredItems = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (needle) {
        return allItems.value.filter((item) => item.name.toLowerCase().includes(needle)
            || (item.name_bn ?? '').includes(search.value.trim()));
    }

    if (activeCategory.value === null) {
        return allItems.value;
    }

    return categories.value.find((category) => category.id === activeCategory.value)?.items ?? [];
});

const selectedTable = computed(() => tables.value.find((table) => table.id === tableId.value) ?? null);

function pickTable(id) {
    tableId.value = id;
    tablePickerOpen.value = false;
}

const openOrderByTable = computed(() => {
    const map = new Map();

    for (const order of props.openOrders) {
        if (order.restaurant_table_id !== null && ! map.has(order.restaurant_table_id)) {
            map.set(order.restaurant_table_id, order);
        }
    }

    return map;
});

const openOrdersWithoutTable = computed(() => props.openOrders.filter((order) => order.restaurant_table_id === null));

const clearBusy = ref(false);

const CLEAR_REASONS = ['wrong_item', 'customer_changed_mind', 'kitchen_mistake', 'duplicate_order', 'payment_failed', 'other'];

function afterClear(tableId = null) {
    if (tableId !== null) {
        const table = tables.value.find((entry) => entry.id === tableId);

        if (table) {
            table.status = 'free';
        }
    }

    router.reload({ only: ['openOrders'], preserveScroll: true });
}

function postClear(url, data, tableId = null) {
    clearBusy.value = true;

    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            afterClear(tableId);
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
        onFinish: () => {
            clearBusy.value = false;
        },
    });
}

/**
 * Free a table. Paid or stale tables are only released; a table with an
 * unpaid running order is force-cleared, which voids that order.
 */
async function clearTable(table) {
    const order = openOrderByTable.value.get(table.id);

    if (! order) {
        const result = await confirm({
            variant: 'warning',
            title: t('orders.free_table'),
            message: table.name,
            confirmText: t('orders.free_table'),
        });

        if (result.confirmed) {
            postClear(route('admin.pos.tables.release', table.id), {}, table.id);
        }

        return;
    }

    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('orders.force_clear'),
        message: t('orders.force_clear_hint'),
        facts: [{ label: table.name, value: `${order.invoice_no} - ${formatMoney(order.total)}` }],
        confirmText: t('orders.force_clear'),
        reasons: CLEAR_REASONS,
    });

    if (result.confirmed) {
        postClear(route('admin.pos.tables.release', table.id), { force: true, reason_code: result.reason }, table.id);
    }
}

async function markOrderPaid(order) {
    const result = await confirm({
        variant: 'success',
        title: t('orders.mark_paid'),
        message: t('orders.mark_paid_hint'),
        facts: [{ label: order.invoice_no, value: formatMoney(order.total) }],
        confirmText: t('orders.mark_paid'),
    });

    if (! result.confirmed) {
        return;
    }

    clearBusy.value = true;

    router.post(route('admin.orders.pay', order.id), { tendered: order.total }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            router.reload({ only: ['openOrders'], preserveScroll: true });
            tables.value = tables.value.map((table) => (table.id === order.restaurant_table_id ? { ...table, status: 'free' } : table));
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
        onFinish: () => {
            clearBusy.value = false;
        },
    });
}

function printOrderInvoice(order) {
    printUrl(route('admin.orders.receipt', order.id));
}

async function clearRunningOrder(order) {
    const result = await confirm({
        variant: 'danger',
        level: 'reason',
        title: t('orders.force_clear'),
        message: t('orders.force_clear_hint'),
        facts: [{ label: order.invoice_no, value: formatMoney(order.total) }],
        confirmText: t('orders.force_clear'),
        reasons: CLEAR_REASONS,
    });

    if (result.confirmed) {
        postClear(route('admin.pos.orders.clear', order.id), { reason_code: result.reason });
    }
}

/**
 * Load a sent but unpaid order back into the cart so items can be added,
 * removed or changed. Saving updates the same invoice.
 */
function editOrder(order) {
    if (cart.value.length > 0 && ! latestOrder.value) {
        holdOrder();
    }

    const lines = [];

    for (const entry of order.items) {
        const item = catalogItem(entry.menu_item_id);

        if (! item) {
            notify.error(t('orders.item_not_available'));

            continue;
        }

        const variant = (item.variants ?? []).find((candidate) => candidate.id === entry.variant_id) ?? null;

        lines.push({
            item,
            variant,
            quantity: entry.quantity,
            addOns: (entry.add_ons ?? []).map((addOn) => ({ id: addOn.id, name: addOn.name, price: Number(addOn.price), qty: addOn.qty })),
            discount: { type: 'percent', value: 0 },
            vatPercent: null,
            extraCharge: 0,
        });
    }

    startNewOrder();
    cart.value = lines;
    orderType.value = order.order_type;
    tableId.value = order.restaurant_table_id;
    customerMobile.value = order.customer?.mobile ?? '';
    discount.value = order.discount_type ? { type: order.discount_type, value: Number(order.discount_value) } : { type: 'percent', value: 0 };
    editingOrder.value = order;
    runningOpen.value = false;
}

/**
 * Empty the cart and every field on the ticket: table, customer, order type
 * and discount. Held orders are not touched.
 */
async function clearAll() {
    const sent = !! latestOrder.value && ! lastPayment.value;
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('orders.clear_all_title'),
        message: sent ? t('orders.clear_all_sent') : t('orders.clear_all_message'),
        facts: [
            { label: t('orders.clear_all_items'), value: String(itemCount.value) },
            { label: t('orders.clear_all_total'), value: formatMoney(latestOrder.value?.total ?? total.value) },
        ],
        confirmText: t('orders.clear_all'),
    });

    if (result.confirmed) {
        startNewOrder();
    }
}

function cancelEditing() {
    startNewOrder();
}

const hasTicketData = computed(() => cart.value.length > 0 || tableId.value !== null || !! customerMobile.value || !! editingOrder.value || !! latestOrder.value || !! lastPayment.value);

const tablesByArea = computed(() => {
    const groups = [];

    for (const table of tables.value) {
        const area = table.area ?? '';
        const existing = groups.find((group) => group.area === area);

        if (existing) {
            existing.tables.push(table);
        } else {
            groups.push({ area, tables: [table] });
        }
    }

    return groups;
});

function itemCategoryName(item) {
    return categories.value.find((category) => category.id === item.menu_category_id)?.name
        ?? categories.value.find((category) => (category.items ?? []).some((row) => row.id === item.id))?.name
        ?? '';
}

function itemPhotoSrc(item) {
    if (! item || failedPhotoIds.value[item.id]) {
        return null;
    }

    const path = item.cover_url || item.photo_path;

    if (! path) {
        return null;
    }

    if (/^https?:\/\//.test(path) || path.startsWith('/')) {
        return path;
    }

    return `/storage/${String(path).replace(/^\/+/, '')}`;
}

function markPhotoFailed(itemId) {
    failedPhotoIds.value = { ...failedPhotoIds.value, [itemId]: true };
}

function itemHasVariants(item) {
    return (item.variants ?? []).length > 0;
}

function sortedVariants(item) {
    return [...(item.variants ?? [])].sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0));
}

function itemCardPrice(item) {
    if (! itemHasVariants(item)) {
        return item.base_price;
    }

    return Math.min(...item.variants.map((variant) => Number(variant.price)));
}

function activeAddOns(item) {
    return [...(item?.add_ons ?? [])]
        .filter((addOn) => addOn.is_active !== false)
        .sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0));
}

function itemHasAddOns(item) {
    return activeAddOns(item).length > 0;
}

function addOnSignature(addOns) {
    return [...(addOns ?? [])].sort((left, right) => left.id - right.id).map((addOn) => `${addOn.id}x${addOn.qty}`).join('.');
}

function addItem(item) {
    if (itemHasAddOns(item)) {
        openOptions(item);

        return;
    }

    if (itemHasVariants(item)) {
        variantPicker.value = item;

        return;
    }

    pushLine(item, null);
}

const menuNumberInput = ref('');
// Round menu-number badge used inside dialog rows (the item cards overlay a smaller one on the photo)
const menuNoBadge = 'flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-primary)_65%,black)] px-1 text-[10px] font-semibold leading-none tabular-nums text-white';

// Branch-wise POS settings: which buttons show and what each one prints
const DEFAULT_ACTIONS = {
    send_to_kitchen: { enabled: true, exact_amount: false, print_invoice: false, print_kitchen: false },
    confirm_payment: { enabled: true, exact_amount: false, print_invoice: false, print_kitchen: false },
    quick_save: { enabled: false, exact_amount: true, print_invoice: true, print_kitchen: false },
};
const posActions = computed(() => {
    const configured = branch.value.pos_settings?.actions ?? {};

    return Object.fromEntries(Object.entries(DEFAULT_ACTIONS).map(([key, defaults]) => [key, { ...defaults, ...(configured[key] ?? {}) }]));
});
const invoiceLayout = computed(() => branch.value.pos_settings?.invoice_layout ?? 'classic');
const primarySpanClass = computed(() => {
    const visible = Object.values(posActions.value).filter((action) => action.enabled).length;

    return { 1: 'col-span-6', 2: 'col-span-3', 3: 'col-span-2' }[visible] ?? 'col-span-6';
});
const mobileTab = ref('menu');

function findByMenuNumber(number) {
    for (const item of allItems.value) {
        if (item.menu_no === number) {
            return { item, variant: null, addOn: null };
        }

        const variant = (item.variants ?? []).find((candidate) => candidate.menu_no === number);

        if (variant) {
            return { item, variant, addOn: null };
        }

        const addOn = activeAddOns(item).find((candidate) => candidate.menu_no === number);

        if (addOn) {
            return { item, variant: null, addOn };
        }
    }

    return null;
}

function menuNumberTarget() {
    const number = Math.round(Number(menuNumberInput.value));

    if (latestOrder.value || ! Number.isFinite(number) || number <= 0) {
        return null;
    }

    const match = findByMenuNumber(number);

    if (! match) {
        notify.error(t('orders.menu_no_not_found', { number }));

        return null;
    }

    const { item, addOn } = match;
    const variant = match.variant
        ?? (itemHasVariants(item) ? (sortedVariants(item).find((candidate) => candidate.is_default) ?? sortedVariants(item)[0]) : null);
    const addOns = addOn ? [{ id: addOn.id, name: addOn.name, name_bn: addOn.name_bn, price: Number(addOn.price), qty: 1 }] : [];

    return { item, variant, addOns };
}

function addByMenuNumber() {
    const target = menuNumberTarget();

    if (target) {
        pushLine(target.item, target.variant, target.addOns);
    }
}

function removeByMenuNumber() {
    const target = menuNumberTarget();

    if (! target) {
        return;
    }

    const line = cart.value.find((entry) => entry.item.id === target.item.id
        && (entry.variant?.id ?? null) === (target.variant?.id ?? null)
        && addOnSignature(entry.addOns) === addOnSignature(target.addOns));

    if (line) {
        setQuantity(line, line.quantity - 1);
    }
}

function variantLine(variant) {
    const item = variantPicker.value;

    if (! item) {
        return null;
    }

    return cart.value.find((line) => line.item.id === item.id
        && line.addOns.length === 0
        && (line.variant?.id ?? null) === (variant?.id ?? null)) ?? null;
}

function variantQuantity(variant) {
    return variantLine(variant)?.quantity ?? 0;
}

function pickerLines() {
    const item = variantPicker.value;

    if (! item) {
        return [];
    }

    return cart.value.filter((line) => line.item.id === item.id && line.variant && line.addOns.length === 0);
}

const pickerQuantity = computed(() => pickerLines().reduce((sum, line) => sum + line.quantity, 0));

const pickerTotal = computed(() => pickerLines().reduce((sum, line) => sum + lineNet(line), 0));

function selectVariant(variant) {
    const item = variantPicker.value;

    if (! item || latestOrder.value) {
        return;
    }

    pushLine(item, variant);
}

function decreaseVariant(variant) {
    const line = variantLine(variant);

    if (! line) {
        return;
    }

    setQuantity(line, line.quantity - 1);
}

function pushLine(item, variant, addOns = [], quantity = 1) {
    const signature = addOnSignature(addOns);
    const existing = cart.value.find((line) => line.item.id === item.id
        && (line.variant?.id ?? null) === (variant?.id ?? null)
        && addOnSignature(line.addOns) === signature);

    if (existing) {
        existing.quantity = Math.min(999, existing.quantity + quantity);
    } else {
        cart.value.push({
            item,
            variant,
            quantity,
            addOns,
            discount: { type: 'percent', value: 0 },
            vatPercent: null,
            extraCharge: 0,
        });
    }
}

function lineUnitPrice(line) {
    return Number(line.variant?.price ?? line.item.base_price);
}

function lineAddOnsTotal(line) {
    return line.addOns.reduce((sum, addOn) => sum + addOn.price * addOn.qty, 0);
}

function lineGross(line) {
    return (lineUnitPrice(line) * line.quantity) + (lineAddOnsTotal(line) * line.quantity);
}

function roundMoney(amount) {
    return Math.round((Number(amount) + Number.EPSILON) * 100) / 100;
}

function lineDiscountAmount(line) {
    const gross = lineGross(line);
    const value = Number(line.discount?.value || 0);

    if (value <= 0) {
        return 0;
    }

    const amount = line.discount?.type === 'fixed' ? value : gross * value / 100;

    return roundMoney(Math.min(Math.max(amount, 0), gross));
}

function lineExtra(line) {
    return roundMoney(Math.max(0, Number(line.extraCharge) || 0));
}

function lineVatRate(line) {
    const value = line.vatPercent;

    if (value === null || value === undefined || value === '') {
        return null;
    }

    const rate = Number(value);

    return Number.isFinite(rate) ? Math.min(100, Math.max(0, rate)) : null;
}

function lineNet(line) {
    return roundMoney(lineGross(line) - lineDiscountAmount(line) + lineExtra(line));
}

function lineKey(line) {
    return `${line.item.id}-${line.variant?.id ?? 0}-${addOnSignature(line.addOns)}`;
}

function vatOn(amount, rate) {
    if (amount <= 0 || rate <= 0) {
        return 0;
    }

    if (branch.value.vat_inclusive) {
        return amount - (amount * 100 / (100 + rate));
    }

    return amount * rate / 100;
}

function openOptions(item, line = null) {
    if (latestOrder.value) {
        return;
    }

    const variants = sortedVariants(item);
    const chosen = line?.variant ?? variants.find((variant) => variant.is_default) ?? variants[0] ?? null;

    optionsDialog.value = {
        item,
        line,
        variantId: chosen?.id ?? null,
        variantQuantities: {},
        variantAddOns: {},
        activeVariantId: null,
        quantity: line?.quantity ?? 1,
        addOns: Object.fromEntries((line?.addOns ?? []).map((addOn) => [addOn.id, addOn.qty])),
    };
}

const optionsVariant = computed(() => {
    const dialog = optionsDialog.value;

    return dialog ? (dialog.item.variants ?? []).find((variant) => variant.id === dialog.variantId) ?? null : null;
});

const optionsAddOnsList = computed(() => {
    const dialog = optionsDialog.value;

    if (! dialog) {
        return [];
    }

    return activeAddOns(dialog.item)
        .map((addOn) => ({ id: addOn.id, name: addOn.name, name_bn: addOn.name_bn, price: Number(addOn.price), qty: dialog.addOns[addOn.id] ?? 0 }))
        .filter((addOn) => addOn.qty > 0);
});

const optionsUnitTotal = computed(() => {
    const dialog = optionsDialog.value;

    if (! dialog) {
        return 0;
    }

    const base = Number(optionsVariant.value?.price ?? dialog.item.base_price);

    return base + optionsAddOnsList.value.reduce((sum, addOn) => sum + addOn.price * addOn.qty, 0);
});

function optionsPicksVariants() {
    const dialog = optionsDialog.value;

    return !! dialog && ! dialog.line && itemHasVariants(dialog.item);
}

function optionVariantQuantity(variant) {
    return optionsDialog.value?.variantQuantities[variant.id] ?? 0;
}

function setOptionVariant(variant, quantity) {
    const dialog = optionsDialog.value;
    const clamped = Math.min(999, Math.max(0, quantity));

    dialog.variantQuantities = { ...dialog.variantQuantities, [variant.id]: clamped };

    if (clamped > 0) {
        dialog.activeVariantId = variant.id;

        return;
    }

    const { [variant.id]: removed, ...remaining } = dialog.variantAddOns;

    dialog.variantAddOns = remaining;

    if (dialog.activeVariantId === variant.id) {
        dialog.activeVariantId = sortedVariants(dialog.item).find((candidate) => (dialog.variantQuantities[candidate.id] ?? 0) > 0)?.id ?? null;
    }
}

// The first click on a size picks it, a click on a picked size that is not active only makes it
// active (to edit its add-ons), and a click on the active size adds one more.
function clickOptionVariant(variant) {
    const dialog = optionsDialog.value;
    const current = optionVariantQuantity(variant);

    if (current > 0 && dialog.activeVariantId !== variant.id) {
        dialog.activeVariantId = variant.id;

        return;
    }

    setOptionVariant(variant, current + 1);
}

const optionsActiveVariant = computed(() => {
    const dialog = optionsDialog.value;

    return dialog ? (dialog.item.variants ?? []).find((variant) => variant.id === dialog.activeVariantId) ?? null : null;
});

function optionAddOnQty(id) {
    const dialog = optionsDialog.value;

    if (optionsPicksVariants()) {
        return dialog.variantAddOns[dialog.activeVariantId]?.[id] ?? 0;
    }

    return dialog.addOns[id] ?? 0;
}

function pickedAddOns(quantities) {
    return activeAddOns(optionsDialog.value.item)
        .map((addOn) => ({ id: addOn.id, name: addOn.name, name_bn: addOn.name_bn, price: Number(addOn.price), qty: quantities?.[addOn.id] ?? 0 }))
        .filter((addOn) => addOn.qty > 0);
}

const optionsPickedVariants = computed(() => {
    const dialog = optionsDialog.value;

    if (! dialog || ! optionsPicksVariants()) {
        return [];
    }

    return sortedVariants(dialog.item)
        .map((variant) => ({ variant, quantity: dialog.variantQuantities[variant.id] ?? 0, addOns: pickedAddOns(dialog.variantAddOns[variant.id]) }))
        .filter((entry) => entry.quantity > 0);
});

const optionsTotal = computed(() => {
    const dialog = optionsDialog.value;

    if (! dialog) {
        return 0;
    }

    if (optionsPicksVariants()) {
        return optionsPickedVariants.value.reduce((sum, entry) => sum
            + (Number(entry.variant.price) + entry.addOns.reduce((total, addOn) => total + addOn.price * addOn.qty, 0)) * entry.quantity, 0);
    }

    return optionsUnitTotal.value * dialog.quantity;
});

function setOptionAddOn(id, qty) {
    const dialog = optionsDialog.value;
    const clamped = Math.min(9, Math.max(0, qty));

    if (! optionsPicksVariants()) {
        dialog.addOns = { ...dialog.addOns, [id]: clamped };

        return;
    }

    // An add-on belongs to one size: with none picked yet, picking an add-on picks the default size.
    if (dialog.activeVariantId === null) {
        const variants = sortedVariants(dialog.item);

        setOptionVariant(variants.find((variant) => variant.is_default) ?? variants[0], 1);
    }

    dialog.variantAddOns = {
        ...dialog.variantAddOns,
        [dialog.activeVariantId]: { ...(dialog.variantAddOns[dialog.activeVariantId] ?? {}), [id]: clamped },
    };
}

function confirmOptions() {
    const dialog = optionsDialog.value;

    if (! dialog) {
        return;
    }

    const variant = optionsVariant.value;
    const addOns = optionsAddOnsList.value;

    if (optionsPicksVariants()) {
        if (optionsPickedVariants.value.length === 0) {
            return;
        }

        optionsPickedVariants.value.forEach((entry) => pushLine(dialog.item, entry.variant, entry.addOns, entry.quantity));
        optionsDialog.value = null;

        return;
    }

    const quantity = Math.min(999, Math.max(1, Math.round(Number(dialog.quantity) || 1)));

    if (dialog.line) {
        const line = dialog.line;
        const duplicate = cart.value.find((entry) => entry !== line
            && entry.item.id === dialog.item.id
            && (entry.variant?.id ?? null) === (variant?.id ?? null)
            && addOnSignature(entry.addOns) === addOnSignature(addOns));

        if (duplicate) {
            duplicate.quantity = Math.min(999, duplicate.quantity + quantity);
            cart.value = cart.value.filter((entry) => entry !== line);
        } else {
            line.variant = variant;
            line.addOns = addOns;
            line.quantity = quantity;
        }
    } else {
        pushLine(dialog.item, variant, addOns, quantity);
    }

    optionsDialog.value = null;
}

function openItemEditor(line) {
    itemEditor.value = {
        key: lineKey(line),
        name: localized(line.item, 'name'),
        quantity: line.quantity,
        discountType: line.discount?.type === 'fixed' ? 'fixed' : 'percent',
        discountValue: Number(line.discount?.value || 0),
        vatPercent: line.vatPercent === null || line.vatPercent === undefined ? '' : line.vatPercent,
        extraCharge: Number(line.extraCharge || 0),
    };
}

function applyItemEditor() {
    const draft = itemEditor.value;
    const line = cart.value.find((entry) => lineKey(entry) === draft?.key);

    if (! line || latestOrder.value) {
        itemEditor.value = null;

        return;
    }

    line.quantity = Math.min(999, Math.max(1, Math.round(Number(draft.quantity) || 1)));
    line.discount = {
        type: draft.discountType === 'fixed' ? 'fixed' : 'percent',
        value: Math.max(0, Number(draft.discountValue) || 0),
    };

    const vat = draft.vatPercent;

    line.vatPercent = vat === '' || vat === null ? null : Math.min(100, Math.max(0, Number(vat) || 0));
    line.extraCharge = Math.max(0, Number(draft.extraCharge) || 0);
    itemEditor.value = null;
}

const grossSubtotal = computed(() => cart.value.reduce((sum, line) => sum + lineGross(line), 0));

const itemDiscountTotal = computed(() => cart.value.reduce((sum, line) => sum + lineDiscountAmount(line), 0));

const subtotal = computed(() => grossSubtotal.value);

const billDiscountAmount = computed(() => {
    const base = Math.max(0, grossSubtotal.value - itemDiscountTotal.value);

    if (discount.value.value <= 0 || base <= 0) {
        return 0;
    }

    const amount = discount.value.type === 'percent'
        ? base * Number(discount.value.value) / 100
        : Number(discount.value.value);

    return roundMoney(Math.min(Math.max(amount, 0), base));
});

const discountAmount = computed(() => roundMoney(itemDiscountTotal.value + billDiscountAmount.value));

const discountPercent = computed(() => (grossSubtotal.value > 0 ? roundMoney(discountAmount.value / grossSubtotal.value * 100) : 0));

const discountBlocked = computed(() => discountPercent.value > 10 && ! props.canOverrideDiscount);

const extraTotal = computed(() => roundMoney(cart.value.reduce((sum, line) => sum + lineExtra(line), 0)));

const hasCustomVat = computed(() => cart.value.some((line) => lineVatRate(line) !== null));

const serviceCharge = computed(() => roundMoney(((subtotal.value - discountAmount.value) * Number(branch.value.service_charge_percent)) / 100));

const vatAmount = computed(() => {
    const branchRate = Number(branch.value.vat_percent);
    const nets = cart.value.map((line) => ({
        net: lineNet(line),
        rate: lineVatRate(line) ?? branchRate,
    }));
    const netTotal = nets.reduce((sum, line) => sum + line.net, 0);
    const bill = billDiscountAmount.value;
    const lineVat = nets.reduce((sum, line) => {
        const share = netTotal > 0 ? (line.net / netTotal) * bill : 0;

        return sum + vatOn(Math.max(0, line.net - share), line.rate);
    }, 0);

    return roundMoney(lineVat + vatOn(serviceCharge.value, branchRate));
});

const vatBase = computed(() => subtotal.value - discountAmount.value + extraTotal.value + serviceCharge.value);

const rawTotal = computed(() => (branch.value.vat_inclusive ? vatBase.value : vatBase.value + vatAmount.value));

const total = computed(() => Math.round(rawTotal.value));

const rounding = computed(() => total.value - rawTotal.value);

// Quantity of each menu item already on the ticket, for the tick on its card.
const selectedQuantity = computed(() => cart.value.reduce((map, line) => {
    map[line.item.id] = (map[line.item.id] ?? 0) + line.quantity;

    return map;
}, {}));

const itemCount = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0));

function setQuantity(line, quantity) {
    if (latestOrder.value) {
        return;
    }

    if (quantity <= 0) {
        cart.value = cart.value.filter((entry) => entry !== line);
    } else {
        line.quantity = Math.min(999, Math.round(quantity));
    }
}

function armIdleLock() {
    clearTimeout(idleTimer);

    if (locked.value) {
        return;
    }

    idleTimer = setTimeout(() => {
        locked.value = true;
    }, Number(props.posPinMinutes || 5) * 60 * 1000);
}

function onActivity() {
    if (! locked.value) {
        armIdleLock();
    }
}

async function unlockPos() {
    pinError.value = '';

    const response = await fetch(route('admin.pos.unlock'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ pin: pin.value }),
    });

    if (! response.ok) {
        pinError.value = t('orders.pin_invalid');

        return;
    }

    pin.value = '';
    locked.value = false;
    armIdleLock();
}

function discountPayload() {
    if (billDiscountAmount.value <= 0) {
        return null;
    }

    return { type: 'fixed', value: Number(billDiscountAmount.value.toFixed(2)) };
}

function startNewOrder() {
    editingOrder.value = null;
    tableId.value = null;
    customerMobile.value = '';
    orderType.value = 'dine_in';
    cart.value = [];
    discount.value = { type: 'percent', value: 0 };
    latestOrder.value = null;
    lastPayment.value = null;
    payLines.value = [blankPayLine()];
    menuNumberInput.value = '';
    itemEditor.value = null;
    sessionStorage.removeItem(TICKET_KEY);
}

async function submitOrder(mode = 'send_to_kitchen') {
    if (latestOrder.value || discountBlocked.value) {
        if (discountBlocked.value) {
            notify.error(t('orders.discount_limit_exceeded'));
        }

        return;
    }

    const payload = {
        order_type: orderType.value,
        restaurant_table_id: orderType.value === 'dine_in' ? tableId.value : null,
        customer_mobile: customerMobile.value || null,
        note: null,
        terminal: TERMINAL,
        items: cart.value.map((line) => {
            const itemRecord = catalogItem(line.item.id) ?? line.item;
            const variant = resolveVariant(itemRecord, line.variant);
            const item = {
                menu_item_id: itemRecord.id,
                variant_id: variant?.id ?? null,
                quantity: line.quantity,
                add_ons: line.addOns.map((addOn) => ({ id: addOn.id, qty: addOn.qty })),
            };
            const discountValue = Number(line.discount?.value || 0);

            if (discountValue > 0) {
                item.discount = {
                    type: line.discount?.type === 'fixed' ? 'fixed' : 'percent',
                    value: discountValue,
                };
            }

            const vatPercent = lineVatRate(line);

            if (vatPercent !== null) {
                item.vat_percent = vatPercent;
            }

            const extraCharge = lineExtra(line);

            if (extraCharge > 0) {
                item.extra_charge = extraCharge;
            }

            return item;
        }),
    };

    const orderDiscount = discountPayload();

    if (orderDiscount) {
        payload.discount = orderDiscount;
    }

    if (! openShift.value) {
        openShiftDialog();
        notify.info(t('orders.shift_needed'));

        return;
    }

    const editing = editingOrder.value;

    if (editing && ! navigator.onLine) {
        notify.error(t('orders.edit_needs_internet'));

        return;
    }

    if (! navigator.onLine) {
        const invoiceNo = await nextLocalInvoice(branch.value.code, TERMINAL, branch.value.pos_settings);
        const queued = await queueOutbox('order', {
            ...payload,
            branch_id: branch.value.id,
            invoice_no: invoiceNo,
        });

        latestOrder.value = {
            local: true,
            id: null,
            client_id: queued.client_id,
            invoice_no: invoiceNo,
            total: total.value,
        };
        notify.info(t('sync.queued'));
        finishSave(mode, latestOrder.value);

        return;
    }

    const visit = editing
        ? (options) => router.put(route('admin.orders.update', editing.id), payload, options)
        : (options) => router.post(route('admin.orders.store'), payload, options);

    visit({
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const order = page.props.flash?.order ?? null;

            editingOrder.value = null;

            if (order?.invoice_no) {
                const sequence = parseInvoiceSequence(branch.value.pos_settings, order.invoice_no, branch.value.code, TERMINAL);

                if (Number.isFinite(sequence) && sequence !== null) {
                    rememberInvoiceCursor(branch.value.code, TERMINAL, sequence);
                }

                latestOrder.value = order;
            }

            notify.success(order?.invoice_no ? `${t(editing ? 'orders.order_updated' : 'orders.sent_to_kitchen')} ${order.invoice_no}` : t('orders.sent_to_kitchen'));

            if (order?.invoice_no) {
                finishSave(mode, order);
            }
        },
        onError: (errors) => {
            // The menu changed since this page loaded (for example after the daily demo reset):
            // refresh it. The cart stays and re-matches the new sizes by name.
            if (Object.keys(errors).some((key) => key.startsWith('items.'))) {
                notify.error(t('orders.catalog_changed'));
                router.reload({ preserveScroll: true });

                return;
            }

            const first = Object.values(errors)[0];
            notify.error(String(first ?? t('common.error_occurred')));
        },
    });
}

// After the order is saved: quick save takes the exact amount at once, the kitchen button prints its slips.
function finishSave(mode, order) {
    if (mode === 'quick_save') {
        payLines.value = [{ method: 'cash', amount: Number(order.total ?? total.value).toFixed(2), reference: '' }];
        payNow('quick_save');

        return;
    }

    printAfter('send_to_kitchen', order);
}

function localInvoiceHtml(order) {
    return localReceiptHtml({
        layout: invoiceLayout.value,
        branch: branch.value,
        invoiceNo: order.invoice_no,
        when: new Date().toLocaleString(),
        orderType: t(`orders.type.${orderType.value}`),
        lines: receiptLines(),
        subtotal: subtotal.value,
        discount: discountAmount.value,
        service: serviceCharge.value,
        extra: extraTotal.value,
        vat: vatAmount.value,
        total: order.total ?? total.value,
        change: changeShown.value,
    });
}

function localKitchenHtml(order) {
    return localKitchenTokenHtml({
        invoiceNo: order.invoice_no,
        when: new Date().toLocaleString(),
        orderType: t(`orders.type.${orderType.value}`),
        table: orderType.value === 'dine_in' ? (tables.value.find((entry) => entry.id === tableId.value)?.name ?? null) : null,
        lines: cart.value.map((line) => ({ name: [localized(line.item, 'name'), line.variant ? localized(line.variant, 'name') : null].filter(Boolean).join(' - '), quantity: line.quantity, addOns: line.addOns })),
    });
}

// Print what the branch settings ask for after this button: customer invoice, kitchen token or both.
function printAfter(action, order) {
    const flags = posActions.value[action];
    const orderId = order?.id ?? null;
    const documents = [];

    if (flags.print_invoice) {
        documents.push(orderId ? { url: route('admin.orders.receipt', orderId) } : { html: localInvoiceHtml(order) });
    }

    if (flags.print_kitchen) {
        documents.push(orderId ? { url: route('admin.orders.kitchen-token', orderId) } : { html: localKitchenHtml(order) });
    }

    printSequence(documents);
}

// Quick save is one tap from cart to printed bill, so the screen is ready for the next customer afterwards.
function finishQuickSave(source) {
    if (source === 'quick_save') {
        startNewOrder();
        mobileTab.value = 'menu';
    }
}

async function payNow(source = 'confirm_payment') {
    const order = pagePropsOrder();

    if (! order) {
        return;
    }

    const tenderedAmount = Number(tendered.value || 0);

    if (tenderedAmount < Number(order.total ?? total.value)) {
        notify.error(t('orders.tendered_too_low'));

        return;
    }

    if (! navigator.onLine || order.local) {
        await queueOfflinePayment(order, {
            method: 'cash',
            tendered: tenderedAmount,
        });
        lastPayment.value = {
            order_id: order.id ?? null,
            client_id: order.client_id ?? null,
            change_due: Math.max(0, tenderedAmount - Number(order.total ?? total.value)),
            invoice_no: order.invoice_no,
        };
        notify.info(t('sync.queued'));
        printAfter(source, order);
        finishQuickSave(source);

        return;
    }

    router.post(route('admin.orders.pay', order.id), {
        tendered: tendered.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            lastPayment.value = page.props.flash?.payment ?? {
                order_id: order.id,
                change_due: changeDue.value,
            };
            notify.success(t('orders.paid_success'));
            printAfter(source, order);
            finishQuickSave(source);
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            notify.error(String(first ?? t('orders.tendered_too_low')));
        },
    });
}

function pagePropsOrder() {
    return latestOrder.value;
}

function holdOrder() {
    if (cart.value.length === 0) {
        return;
    }

    heldOrders.value.push({
        id: Date.now(),
        cart: JSON.parse(JSON.stringify(cart.value)),
        orderType: orderType.value,
        tableId: tableId.value,
        customerMobile: customerMobile.value,
        at: new Date().toISOString(),
    });

    startNewOrder();
    notify.info(t('orders.held'));
}

function resumeOrder(held) {
    cart.value = held.cart;
    orderType.value = held.orderType;
    tableId.value = held.tableId;
    customerMobile.value = held.customerMobile;
    heldOrders.value = heldOrders.value.filter((entry) => entry.id !== held.id);
}

function discardHeld(held) {
    heldOrders.value = heldOrders.value.filter((entry) => entry.id !== held.id);
}

function openShiftDialog() {
    openingCash.value = '0.00';
    shiftDialogOpen.value = true;
}

async function queueOfflinePayment(order, payload) {
    const queuedOrder = order.client_id ? await findOutbox(order.client_id) : null;

    if (queuedOrder && payload.method === 'cash') {
        await updateOutboxPayload(order.client_id, { tendered: payload.tendered });

        return;
    }

    await queueOutbox('payment', {
        branch_id: branch.value.id,
        order_client_id: order.client_id ?? null,
        order_id: order.id ?? null,
        ...payload,
    });
}

function receiptLines() {
    return cart.value.map((line) => ({
        name: localized(line.item, 'name'),
        quantity: line.quantity,
        total: lineNet(line),
    }));
}

async function startShift() {
    if (! navigator.onLine) {
        await queueOutbox('shift', {
            action: 'open',
            branch_id: branch.value.id,
            opening_cash: openingCash.value,
            terminal: TERMINAL,
        });
        localShift.value = {
            id: 'local',
            opening_cash: openingCash.value,
            terminal: TERMINAL,
        };
        await rememberLocalShift(localShift.value);
        shiftDialogOpen.value = false;
        notify.info(t('sync.queued'));

        return;
    }

    router.post(route('admin.shifts.open'), {
        opening_cash: openingCash.value,
        terminal: TERMINAL,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            shiftDialogOpen.value = false;
            notify.success(t('orders.shift_opened'));
        },
    });
}

function printCustomerCopy() {
    const order = latestOrder.value;
    const orderId = lastPayment.value?.order_id ?? order?.id;

    if (! orderId && order?.invoice_no) {
        printHtml(localInvoiceHtml(order));

        return;
    }

    if (! orderId) {
        notify.info(t('orders.send_before_print'));

        return;
    }

    printUrl(route('admin.orders.receipt', orderId));
}

// Keyboard shortcuts. The list shown in the help window is shortcutGroups below; keep both in step.
const shortcutsOpen = ref(false);

const shortcutGroups = computed(() => [
    {
        title: t('orders.sc_group_find'),
        items: [
            { keys: ['F2'], label: t('orders.sc_item_no') },
            { keys: ['F3', '/'], label: t('orders.sc_search') },
            { keys: ['Enter'], label: t('orders.sc_add_by_no') },
            { keys: ['Shift+Enter'], label: t('orders.sc_remove_by_no') },
        ],
    },
    {
        title: t('orders.sc_group_order'),
        items: [
            { keys: ['F4', 'Ctrl+Enter'], label: t('orders.sc_send') },
            { keys: ['F6'], label: t('orders.sc_quick') },
            { keys: ['F8'], label: t('orders.sc_hold') },
            { keys: ['Alt+N'], label: t('orders.sc_new') },
            { keys: ['Alt+X'], label: t('orders.sc_clear') },
        ],
    },
    {
        title: t('orders.sc_group_payment'),
        items: [
            { keys: ['Alt+1', '...', 'Alt+9'], label: t('orders.sc_method') },
            { keys: ['Alt+E'], label: t('orders.sc_exact') },
            { keys: ['F9'], label: t('orders.sc_confirm') },
            { keys: ['F10'], label: t('orders.sc_print') },
        ],
    },
    {
        title: t('orders.sc_group_general'),
        items: [
            { keys: ['F1', '?'], label: t('orders.sc_help') },
            { keys: ['Esc'], label: t('orders.sc_close') },
        ],
    },
]);

function focusField(selector, tab) {
    mobileTab.value = tab;

    nextTick(() => {
        const field = document.querySelector(selector);

        field?.focus();
        field?.select?.();
    });
}

function canSendOrder() {
    return cart.value.length > 0 && ! latestOrder.value && ! discountBlocked.value;
}

function runShortcut(action) {
    switch (action) {
        case 'help': shortcutsOpen.value = ! shortcutsOpen.value; break;
        case 'item_no': focusField('[data-pos-menu-number]', 'order'); break;
        case 'search': focusField('[data-pos-search]', 'menu'); break;
        case 'send':
            if (posActions.value.send_to_kitchen.enabled && canSendOrder()) {
                submitOrder('send_to_kitchen');
            }

            break;
        case 'quick':
            if (posActions.value.quick_save.enabled && payMethods.value.includes('cash') && canSendOrder()) {
                submitOrder('quick_save');
            }

            break;
        case 'hold':
            if (cart.value.length > 0 && ! latestOrder.value) {
                holdOrder();
            }

            break;
        case 'new':
            if (latestOrder.value || lastPayment.value) {
                startNewOrder();
            }

            break;
        case 'clear':
            if (hasTicketData.value) {
                clearAll();
            }

            break;
        case 'confirm':
            if (posActions.value.confirm_payment.enabled && canConfirmPayment.value) {
                confirmPayment();
            }

            break;
        case 'print':
            if (latestOrder.value || lastPayment.value) {
                printCustomerCopy();
            }

            break;
        case 'exact':
            if (! lastPayment.value && payLines.value.length === 1 && methodType(payMethod.value) === 'cash') {
                quickAmount(orderDue());
            }

            break;
        default:
            if (action.startsWith('method:') && ! lastPayment.value) {
                const method = payMethods.value[Number(action.slice(7)) - 1];

                if (method) {
                    payMethod.value = method;
                }
            }
    }
}

function shortcutFor(event) {
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    const modifier = event.ctrlKey || event.metaKey;

    if (modifier && event.key === 'Enter') {
        return 'send';
    }

    if (event.altKey && ! modifier) {
        const byCode = { KeyN: 'new', KeyX: 'clear', KeyE: 'exact' };

        if (byCode[event.code]) {
            return byCode[event.code];
        }

        if (/^Digit[1-9]$/.test(event.code)) {
            return `method:${event.code.slice(5)}`;
        }
    }

    if (modifier || event.altKey) {
        return null;
    }

    const byKey = { F1: 'help', F2: 'item_no', F3: 'search', F4: 'send', F6: 'quick', F8: 'hold', F9: 'confirm', F10: 'print' };

    if (byKey[event.key]) {
        return byKey[event.key];
    }

    if (! typing && event.key === '/') {
        return 'search';
    }

    if (! typing && event.key === '?') {
        return 'help';
    }

    return null;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        if (shortcutsOpen.value) {
            shortcutsOpen.value = false;
        } else if (optionsDialog.value) {
            optionsDialog.value = null;
        } else if (variantPicker.value) {
            variantPicker.value = null;
        } else if (itemEditor.value) {
            itemEditor.value = null;
        }

        return;
    }

    if (locked.value || confirmState.open) {
        return;
    }

    const action = shortcutFor(event);

    if (action === null) {
        return;
    }

    // The help window opens from anywhere; every other shortcut waits until the item windows are closed.
    if (action !== 'help' && (shortcutsOpen.value || variantPicker.value || itemEditor.value || optionsDialog.value)) {
        return;
    }

    event.preventDefault();
    runShortcut(action);
}

async function refreshOfflineCache() {
    try {
        if (categories.value.length === 0) {
            const cached = await getCachedMenu();

            if ((cached.categories ?? []).length > 0) {
                categories.value = cached.categories;
                tables.value = cached.tables ?? [];

                if (cached.branch) {
                    branch.value = { ...branch.value, ...cached.branch };
                }

                reconcileCart();
            }
        }

        if (! props.openShift) {
            localShift.value = await getLocalShift();
        }

        await cacheBootstrap({
            categories: categories.value,
            tables: tables.value,
            branch: branch.value,
            data_version: await getDataVersion(),
        });
    } catch {
        return;
    }

    if (! navigator.onLine) {
        return;
    }

    try {
        const response = await fetch('/api/v1/sync/bootstrap', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (! response.ok) {
            return;
        }

        const data = (await response.json()).data ?? {};

        await cacheBootstrap(data);

        const code = data.branch?.code ?? branch.value.code;

        for (const row of data.invoice_sequences ?? []) {
            await rememberInvoiceCursor(code, row.terminal_code, row.last_number, row.for_date);
        }
    } catch {
        // The cursor already on the device stays in place.
    }
}

useIncomingOrders(props.branch?.id ?? branch.value?.id);

onMounted(() => {
    reconcileCart();

    // A method the branch has turned off since this ticket was saved falls back to the first one on offer.
    if (! lastPayment.value) {
        payLines.value.forEach((line) => {
            if (! payMethods.value.includes(line.method)) {
                line.method = payMethods.value[0];
            }
        });
    }

    window.addEventListener('keydown', onKeydown);
    window.addEventListener('pointerdown', onActivity);
    window.addEventListener('keydown', onActivity);
    armIdleLock();
    refreshOfflineCache();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('pointerdown', onActivity);
    window.removeEventListener('keydown', onActivity);
    clearTimeout(idleTimer);
});

function quickAmount(amount) {
    tendered.value = String(amount);
}

function orderDue() {
    return Number(latestOrder.value?.total ?? total.value);
}

function needsReference(method) {
    return methodType(method) === 'wallet';
}

// A single non-cash payment always covers the whole bill, so its amount box shows the total.
function syncSingleAmount() {
    const line = payLines.value[0];

    if (payLines.value.length === 1 && methodType(line.method) !== 'cash' && ! lastPayment.value) {
        const due = orderDue();

        line.amount = due > 0 ? String(due) : '';
    }
}

watch([() => payLines.value[0].method, () => payLines.value.length, () => latestOrder.value?.total, total], syncSingleAmount);

function payLinesTotal() {
    return payLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0);
}

// The + button adds another payment row for the part of the bill still unpaid.
function addPayLine() {
    if (lastPayment.value) {
        return;
    }

    const due = orderDue();

    if (payLines.value.length === 1 && (! Number(payLines.value[0].amount) || Math.abs(Number(payLines.value[0].amount) - due) < 0.01)) {
        payLines.value[0].amount = String(Math.ceil(due / 2));
    }

    const remaining = Math.max(0, due - payLinesTotal());
    const used = payLines.value.map((line) => line.method);

    payLines.value.push({
        method: payMethods.value.find((method) => ! used.includes(method)) ?? payMethods.value[0],
        amount: remaining > 0 ? String(remaining) : '',
        reference: '',
    });
}

function removePayLine(index) {
    if (index > 0 && ! lastPayment.value) {
        payLines.value.splice(index, 1);
    }
}

function paySplit() {
    const order = latestOrder.value;

    if (! order) {
        return;
    }

    if (! navigator.onLine || order.local) {
        queueOfflinePayment(order, {
            method: 'split',
            lines: payLines.value.map((line) => ({
                method: line.method,
                amount: line.amount,
                reference: line.reference || null,
            })),
        }).then(() => {
            lastPayment.value = {
                order_id: order.id ?? null,
                client_id: order.client_id ?? null,
                change_due: 0,
                invoice_no: order.invoice_no,
            };
            notify.info(t('sync.queued'));
            printAfter('confirm_payment', order);
        });

        return;
    }

    router.post(route('admin.payments.split', order.id), {
        lines: payLines.value.map((line) => ({
            method: line.method,
            amount: line.amount,
            reference: line.reference || null,
        })),
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            lastPayment.value = page.props.flash?.payment ?? {
                order_id: order.id,
                change_due: 0,
            };
            notify.success(t('payments.split_completed'));
            printAfter('confirm_payment', order);
        },
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('payments.split_mismatch')),
    });
}

function payWalletManual() {
    const order = latestOrder.value;

    if (! order) {
        return;
    }

    if (! navigator.onLine || order.local) {
        queueOfflinePayment(order, {
            method: payMethod.value,
            amount: Number(order.total ?? total.value),
            reference: walletTrxId.value,
        }).then(() => {
            lastPayment.value = {
                order_id: order.id ?? null,
                client_id: order.client_id ?? null,
                change_due: 0,
                invoice_no: order.invoice_no,
            };
            notify.info(t('sync.queued'));
            printAfter('confirm_payment', order);
        });

        return;
    }

    router.post(route('admin.payments.manual', order.id), {
        method: payMethod.value,
        amount: Number(latestOrder.value?.total ?? total.value),
        reference: walletTrxId.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            lastPayment.value = page.props.flash?.payment ?? {
                order_id: order.id,
                change_due: 0,
            };
            notify.success(t('payments.manual_recorded'));
            printAfter('confirm_payment', order);
        },
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('common.error_occurred')),
    });
}

function paySingle() {
    const order = latestOrder.value;

    if (! order) {
        return;
    }

    if (! navigator.onLine || order.local) {
        queueOfflinePayment(order, {
            method: payMethod.value,
            amount: Number(order.total ?? total.value),
            reference: payLines.value[0].reference || null,
        }).then(() => {
            lastPayment.value = {
                order_id: order.id ?? null,
                client_id: order.client_id ?? null,
                change_due: 0,
                invoice_no: order.invoice_no,
            };
            notify.info(t('sync.queued'));
            printAfter('confirm_payment', order);
        });

        return;
    }

    router.post(route('admin.payments.single', order.id), {
        method: payMethod.value,
        reference: payLines.value[0].reference || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            lastPayment.value = page.props.flash?.payment ?? { order_id: order.id, change_due: 0 };
            notify.success(page.props.flash?.success ?? t('payments.manual_recorded'));
            printAfter('confirm_payment', order);
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

// One confirm button serves a single payment (cash, wallet, card) and a split across rows.
function confirmPayment() {
    if (payLines.value.length > 1) {
        paySplit();
    } else if (payMethod.value === 'cash') {
        payNow();
    } else if (methodType(payMethod.value) === 'wallet') {
        payWalletManual();
    } else {
        paySingle();
    }
}

const payLinesReady = computed(() => {
    if (payLines.value.some((line) => needsReference(line.method) && ! line.reference)) {
        return false;
    }

    if (payLines.value.length === 1) {
        const line = payLines.value[0];

        return methodType(line.method) === 'cash' || ! Number(line.amount) || Math.abs(Number(line.amount) - orderDue()) < 0.01;
    }

    return Math.abs(payLinesTotal() - orderDue()) < 0.01;
});
const canConfirmPayment = computed(() => !! latestOrder.value && ! lastPayment.value && payLinesReady.value);
const confirmPaymentLabel = computed(() => (payMode.value === 'wallet' ? t('payments.record_manual') : t('orders.confirm_payment')));
const laneLocked = computed(() => !! lastPayment.value);
const discountAllTotal = computed(() => itemDiscountTotal.value + billDiscountAmount.value);
const changeShown = computed(() => (lastPayment.value ? Number(lastPayment.value.change_due ?? 0) : changeDue.value));

// Exact amount: put the bill total in the received box once an order is saved.
watch(() => [latestOrder.value, payMode.value], () => {
    if (posActions.value.confirm_payment.exact_amount && latestOrder.value && ! lastPayment.value && payMode.value === 'cash' && ! tendered.value) {
        tendered.value = Number(latestOrder.value.total ?? total.value).toFixed(2);
    }
});

const changeDue = computed(() => Math.max(0, Number(tendered.value || 0) - (latestOrder.value ? Number(latestOrder.value.total) : total.value)));
</script>

<template>
    <div class="flex h-[calc(100vh-3.5rem-var(--demo-banner-h))] flex-col gap-2 overflow-hidden p-2 lg:flex-row">
        <!-- Phones show the menu and the order one at a time -->
        <div class="grid shrink-0 grid-cols-2 gap-1 lg:hidden" role="tablist">
            <button
                type="button"
                role="tab"
                :aria-selected="mobileTab === 'menu'"
                class="flex h-8 items-center justify-center gap-1.5 rounded-md border text-[13px] font-semibold"
                :class="mobileTab === 'menu' ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted'"
                @click="mobileTab = 'menu'"
            >
                <AppIcon name="utensils" size="12" />
                {{ t('orders.mobile_menu_tab') }}
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="mobileTab === 'order'"
                class="flex h-8 items-center justify-center gap-1.5 rounded-md border text-[13px] font-semibold"
                :class="mobileTab === 'order' ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted'"
                @click="mobileTab = 'order'"
            >
                <AppIcon name="receipt" size="12" />
                {{ t('orders.mobile_order_tab') }}
                <span v-if="itemCount > 0" class="flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold tabular-nums text-accent-foreground">{{ itemCount }}</span>
            </button>
        </div>

        <!-- Left: categories + items -->
        <section class="min-h-0 min-w-0 flex-1 flex-col gap-2" :class="mobileTab === 'order' ? 'hidden lg:flex' : 'flex'">
            <div class="flex flex-wrap items-center gap-1" role="tablist">
                <div class="flex h-7 w-full items-center gap-1.5 rounded-md border border-border bg-surface px-2 sm:w-52">
                    <AppIcon name="magnifying-glass" size="12" class="text-muted" />
                    <input
                        ref="searchRef"
                        v-model="search"
                        data-pos-search
                        type="search"
                        :placeholder="t('menu.search_items')"
                        class="w-full border-0 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted/60"
                    >
                </div>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeCategory === null"
                    class="h-7 rounded-md border px-2 text-[13px] font-medium leading-none"
                    :class="activeCategory === null ? 'border-accent bg-accent/15 text-primary' : 'border-border bg-surface text-muted hover:text-foreground'"
                    @click="activeCategory = null"
                >
                    {{ t('menu.all_items') }}
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    role="tab"
                    :aria-selected="activeCategory === category.id"
                    class="h-7 rounded-md border px-2 text-[13px] font-medium leading-none"
                    :class="activeCategory === category.id ? 'border-accent bg-accent/15 text-primary' : 'border-border bg-surface text-muted hover:text-foreground'"
                    @click="activeCategory = category.id"
                >
                    {{ category.name }}
                </button>
                <button
                    type="button"
                    class="ml-auto flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted hover:text-foreground"
                    :aria-label="t('orders.shortcuts_title')"
                    :title="`${t('orders.shortcuts_title')} (F1)`"
                    @click="shortcutsOpen = true"
                >
                    <AppIcon name="keyboard" size="13" />
                </button>
            </div>

            <div class="thin-scrollbar grid flex-1 grid-cols-2 content-start gap-2 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="item in filteredItems"
                    :key="item.id"
                    type="button"
                    class="relative flex min-h-16 items-center gap-2 rounded-lg border p-2 text-left transition-colors hover:border-accent"
                    :class="selectedQuantity[item.id] ? 'border-accent bg-accent/20 ring-1 ring-accent' : 'border-border bg-surface'"
                    @click="addItem(item)"
                >
                    <span class="pointer-events-none absolute left-0.5 top-0.5 z-10 flex h-4 min-w-4 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-primary)_65%,black)] px-1 text-[9px] font-semibold leading-none tabular-nums text-white">{{ item.menu_no }}</span>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-background">
                        <img
                            v-if="itemPhotoSrc(item)"
                            :src="itemPhotoSrc(item)"
                            alt=""
                            class="h-full w-full object-cover"
                            @error="markPhotoFailed(item.id)"
                        >
                        <AppIcon v-else name="utensils" size="16" class="text-muted" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="line-clamp-2 text-sm font-medium text-foreground">{{ localized(item, 'name') }}</span>
                        <span class="flex items-center justify-between gap-1">
                            <span class="inline-flex items-baseline gap-1 text-sm font-semibold tabular-nums text-foreground">
                                <span v-if="itemHasVariants(item)" class="text-[11px] font-medium uppercase tracking-wide text-muted">{{ t('orders.from_price') }}</span>
                                <span>{{ formatMoney(itemCardPrice(item)) }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <AppIcon v-if="itemHasVariants(item) || itemHasAddOns(item)" name="layer-group" size="12" class="text-muted" />
                            </span>
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <!-- Right: cart -->
        <section class="min-h-0 w-full flex-1 shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-surface lg:h-full lg:w-[26rem] lg:flex-none" :class="mobileTab === 'menu' ? 'hidden lg:flex' : 'flex'">
            <div v-if="editingOrder" class="flex items-center justify-between gap-2 border-b border-warning/40 bg-warning/15 px-2 py-1 text-[13px] text-foreground">
                <span class="flex min-w-0 items-center gap-1.5">
                    <AppIcon name="pen-to-square" size="12" />
                    <span class="truncate font-medium">{{ t('orders.editing_order', { invoice: editingOrder.invoice_no }) }}</span>
                </span>
                <button type="button" class="h-6 shrink-0 rounded-md border border-border bg-surface px-2 text-xs font-medium hover:text-danger" @click="cancelEditing">{{ t('common.cancel') }}</button>
            </div>

            <!-- Selected items -->
            <div class="thin-scrollbar min-h-0 flex-1 overflow-y-auto px-2">
                <p v-if="cart.length === 0" class="py-6 text-center text-[13px] text-muted">{{ t('orders.cart_empty') }}</p>

                <div v-for="line in cart" :key="lineKey(line)" class="border-b border-border py-1.5 last:border-b-0">
                    <div class="grid grid-cols-[minmax(0,1fr)_5.75rem_5rem_1.75rem_1.75rem_1.75rem] items-center gap-1">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium text-foreground">{{ localized(line.item, 'name') }}</p>
                            <p v-if="line.variant" class="truncate text-xs text-muted">{{ localized(line.variant, 'name') }}</p>
                            <p v-if="(line.addOns ?? []).length" class="truncate text-xs text-muted">{{ line.addOns.map((addOn) => (addOn.qty > 1 ? `${addOn.qty}x ` : '+ ') + (addOn.name)).join(', ') }}</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-foreground disabled:opacity-50" :disabled="!!latestOrder" :aria-label="t('common.decrease')" @click.stop="setQuantity(line, line.quantity - 1)">
                                <AppIcon name="minus" size="12" />
                            </button>
                            <span class="w-9 text-center text-[13px] font-semibold tabular-nums text-foreground">{{ line.quantity }}</span>
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-foreground disabled:opacity-50" :disabled="!!latestOrder || line.quantity >= 999" :aria-label="t('common.increase')" @click.stop="setQuantity(line, line.quantity + 1)">
                                <AppIcon name="plus" size="12" />
                            </button>
                        </div>
                        <span class="whitespace-nowrap text-right text-[13px] font-semibold tabular-nums text-foreground">{{ formatMoney(lineNet(line)) }}</span>
                        <button
                            v-if="itemHasAddOns(line.item)"
                            type="button"
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border disabled:opacity-50"
                            :class="line.addOns.length > 0 ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted hover:text-foreground'"
                            :aria-label="t('orders.add_ons')"
                            :disabled="!!latestOrder"
                            @click.stop="openOptions(line.item, line)"
                        >
                            <AppIcon name="layer-group" size="12" />
                        </button>
                        <span v-else class="h-7 w-7 shrink-0" aria-hidden="true" />
                        <button
                            type="button"
                            class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-md border disabled:opacity-50"
                            :class="lineDiscountAmount(line) > 0 || lineExtra(line) > 0 || lineVatRate(line) !== null ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted hover:text-foreground'"
                            :aria-label="t('orders.edit_item')"
                            :disabled="!!latestOrder"
                            @click.stop="openItemEditor(line)"
                        >
                            <AppIcon name="pen-to-square" size="12" />
                        </button>
                        <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted hover:text-danger disabled:opacity-50" :disabled="!!latestOrder" :aria-label="t('common.remove')" @click="setQuantity(line, 0)">
                            <AppIcon name="xmark" size="12" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Operations: every control is always visible; unavailable ones are disabled -->
            <footer class="thin-scrollbar max-h-[65%] shrink-0 space-y-[3px] overflow-y-auto border-t border-border bg-surface px-2 py-1">
                <!-- Held orders and current invoice -->
                <div class="flex h-5 items-center gap-1.5 text-xs">
                    <span class="shrink-0 font-medium text-muted">{{ t('orders.held_orders') }} ({{ heldOrders.length }})</span>
                    <div class="thin-scrollbar flex min-w-0 flex-1 items-center gap-1 overflow-x-auto">
                        <button
                            v-for="held in heldOrders"
                            :key="held.id"
                            type="button"
                            class="flex h-5 shrink-0 items-center gap-1 rounded-md border border-border bg-background px-1.5 text-xs text-foreground hover:border-accent"
                            @click="resumeOrder(held)"
                        >
                            <AppIcon name="clock" size="10" class="text-warning" />
                            {{ held.cart.reduce((sum, line) => sum + line.quantity, 0) }}
                            <span class="text-muted hover:text-danger" role="button" :aria-label="t('common.remove')" @click.stop="discardHeld(held)">
                                <AppIcon name="xmark" size="9" />
                            </span>
                        </button>
                    </div>
                    <form class="flex h-5 shrink-0 items-stretch overflow-hidden rounded border border-border bg-background focus-within:border-accent" @submit.prevent="addByMenuNumber">
                        <button type="button" class="flex w-5 shrink-0 items-center justify-center border-r border-border text-foreground hover:bg-surface disabled:opacity-40" :disabled="!!latestOrder || ! menuNumberInput" :aria-label="t('common.decrease')" :title="t('common.decrease')" @click="removeByMenuNumber">
                            <AppIcon name="minus" size="10" />
                        </button>
                        <input
                            v-model="menuNumberInput"
                            data-pos-menu-number
                            type="number"
                            min="1"
                            inputmode="numeric"
                            :placeholder="t('orders.menu_no_placeholder')"
                            :aria-label="t('orders.menu_no_placeholder')"
                            :disabled="!!latestOrder"
                            @keydown.enter.shift.prevent="removeByMenuNumber"
                            class="w-14 min-w-0 border-0 bg-transparent px-1 text-center text-[11px] tabular-nums text-foreground outline-none [appearance:textfield] disabled:opacity-50 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        >
                        <button type="submit" class="flex w-5 shrink-0 items-center justify-center border-l border-border bg-accent text-accent-foreground hover:opacity-90 disabled:opacity-40" :disabled="!!latestOrder || ! menuNumberInput" :aria-label="t('orders.menu_no_add')" :title="t('orders.menu_no_add')">
                            <AppIcon name="plus" size="10" />
                        </button>
                    </form>
                    <button type="button" :disabled="! hasTicketData" class="inline-flex h-5 shrink-0 items-center gap-1 rounded-md px-1 text-[11px] font-medium text-danger hover:underline disabled:cursor-not-allowed disabled:text-muted disabled:opacity-50 disabled:no-underline" @click="clearAll">
                        <AppIcon name="trash" size="10" />
                        {{ t('orders.clear_all') }}
                    </button>
                    <span data-pos-invoice class="shrink-0 font-mono text-[11px] text-muted" :title="latestOrder?.invoice_no">{{ latestOrder?.invoice_no ?? '-' }}</span>
                </div>

                <!-- Order type and shift -->
                <div class="flex items-center gap-1">
                    <div class="flex flex-1 gap-1" role="radiogroup" :aria-label="t('orders.type_label')">
                        <button
                            v-for="type in ['dine_in', 'takeaway']"
                            :key="type"
                            type="button"
                            role="radio"
                            :aria-checked="orderType === type"
                            class="inline-flex h-7 flex-1 items-center justify-center rounded-md border px-2 text-[13px] font-medium"
                            :class="orderType === type ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                            @click="orderType = type"
                        >
                            {{ t(`orders.type.${type}`) }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md border px-2 text-xs font-medium disabled:cursor-default"
                        :class="openShift ? 'border-success/40 bg-success/10 text-success' : 'border-accent bg-accent text-accent-foreground hover:opacity-90'"
                        :disabled="!! openShift"
                        @click="openShiftDialog"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="openShift ? 'bg-success' : 'bg-foreground'" />
                        {{ openShift ? t('orders.shift_open_status') : t('orders.open_shift') }}
                    </button>
                </div>

                <!-- Table and customer mobile -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="orderType !== 'dine_in'"
                        :aria-label="t('orders.select_table')"
                        class="flex h-7 w-28 shrink-0 items-center justify-between gap-1 rounded-md border px-1.5 text-[13px] outline-none focus-visible:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                        :class="selectedTable ? 'border-accent bg-accent/15 font-semibold text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                        @click="tablePickerOpen = true"
                    >
                        <span class="flex min-w-0 items-center gap-1">
                            <AppIcon name="chair" size="12" />
                            <span class="truncate">{{ selectedTable ? selectedTable.name : t('orders.no_table') }}</span>
                        </span>
                        <AppIcon name="chevron-down" size="10" class="shrink-0 text-muted" />
                    </button>
                    <button
                        type="button"
                        :aria-label="t('orders.running_orders')"
                        :title="t('orders.running_orders')"
                        class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-background text-muted hover:text-foreground"
                        @click="runningOpen = true"
                    >
                        <AppIcon name="clipboard-list" size="13" />
                        <span v-if="openOrders.length" class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground">{{ openOrders.length }}</span>
                    </button>
                    <div class="min-w-0 flex-1">
                        <MobileInput v-model="customerMobile" :placeholder="t('orders.customer_mobile')" />
                    </div>
                </div>

                <!-- Bill discount -->
                <div class="flex items-center gap-1 rounded-md bg-background px-1.5 py-0.5">
                    <span class="shrink-0 text-[13px] font-medium text-foreground">{{ t('orders.bill_discount') }}</span>
                    <button type="button" class="h-6 w-7 rounded-md border text-[13px] font-medium disabled:opacity-50" :disabled="!! latestOrder" :class="discount.type !== 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="discount.type = 'percent'">%</button>
                    <button type="button" class="h-6 w-7 rounded-md border text-[13px] font-medium disabled:opacity-50" :disabled="!! latestOrder" :class="discount.type === 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="discount.type = 'fixed'">৳</button>
                    <input v-model.number="discount.value" :disabled="!! latestOrder" type="number" min="0" :aria-label="t('orders.bill_discount')" class="h-6 w-16 rounded-md border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent disabled:opacity-60" :class="discountBlocked ? 'border-danger' : 'border-border'">
                    <span class="ml-auto min-w-0 truncate text-xs font-medium tabular-nums" :class="discountBlocked || billDiscountAmount > 0 ? 'text-danger' : 'text-muted'" :title="discountBlocked ? t('orders.discount_limit_hint') : undefined">
                        {{ discountBlocked ? t('orders.discount_limit_short') : `-${formatMoney(billDiscountAmount)}` }}
                    </span>
                </div>

                <!-- Charges at a glance: five fixed cells -->
                <dl class="grid grid-cols-5 divide-x divide-border rounded-md border border-border bg-background text-center">
                    <div class="min-w-0 px-1 py-0.5">
                        <dt class="truncate text-[10px] leading-tight text-muted">{{ t('receipt.subtotal') }}</dt>
                        <dd class="text-xs font-medium tabular-nums text-foreground">{{ formatMoney(subtotal) }}</dd>
                    </div>
                    <div class="min-w-0 px-1 py-0.5">
                        <dt class="truncate text-[10px] leading-tight text-muted">{{ t('orders.discount_short') }}</dt>
                        <dd class="text-xs font-medium tabular-nums" :class="discountAllTotal > 0 ? 'text-danger' : 'text-muted'">{{ discountAllTotal > 0 ? '-' : '' }}{{ formatMoney(discountAllTotal) }}</dd>
                    </div>
                    <div class="min-w-0 px-1 py-0.5">
                        <dt class="truncate text-[10px] leading-tight text-muted" :title="t('receipt.service_charge')">{{ t('orders.service_short') }}</dt>
                        <dd class="text-xs font-medium tabular-nums" :class="serviceCharge > 0 ? 'text-foreground' : 'text-muted'">{{ formatMoney(serviceCharge) }}</dd>
                    </div>
                    <div class="min-w-0 px-1 py-0.5">
                        <dt class="truncate text-[10px] leading-tight text-muted" :title="t('orders.other_charge')">{{ t('orders.other_short') }}</dt>
                        <dd class="text-xs font-medium tabular-nums" :class="extraTotal > 0 ? 'text-foreground' : 'text-muted'">{{ formatMoney(extraTotal) }}</dd>
                    </div>
                    <div class="min-w-0 px-1 py-0.5">
                        <dt class="truncate text-[10px] leading-tight text-muted">{{ t('receipt.vat') }}{{ hasCustomVat ? '' : ` ${Number(branch.vat_percent)}%` }}</dt>
                        <dd class="text-xs font-medium tabular-nums" :class="vatAmount > 0 ? 'text-foreground' : 'text-muted'">{{ formatMoney(vatAmount) }}</dd>
                    </div>
                </dl>

                <!-- Total and change -->
                <div class="flex items-stretch gap-1">
                    <div class="flex h-8 min-w-0 flex-1 items-center justify-between rounded-md bg-primary px-2.5 text-primary-foreground">
                        <span class="text-xs opacity-80">{{ t('receipt.grand_total') }}</span>
                        <span class="text-lg font-bold tabular-nums">{{ formatMoney(total) }}</span>
                    </div>
                    <div class="flex h-8 w-28 shrink-0 flex-col justify-center rounded-md bg-success/10 px-2">
                        <span class="truncate text-[10px] leading-tight text-muted">{{ t('orders.change_due') }}</span>
                        <span class="text-sm font-semibold leading-tight tabular-nums" :class="changeShown > 0 ? 'text-success' : 'text-muted'">{{ formatMoney(changeShown) }}</span>
                    </div>
                </div>

                <!-- Payment: one row per payment, + adds another method -->
                <div class="space-y-1">
                    <div v-for="(line, index) in payLines" :key="index" class="flex items-center gap-1 rounded-md border border-border bg-background px-1.5 py-0.5">
                        <select v-model="line.method" :disabled="laneLocked" :aria-label="t('payments.method')" class="h-6 w-[4.5rem] shrink-0 rounded-md border border-border bg-surface px-0.5 text-xs font-medium text-foreground outline-none focus:border-accent disabled:opacity-50">
                            <option v-for="entry in payMethodList" :key="entry.method" :value="entry.method">{{ localized(entry, 'name') }}</option>
                        </select>
                        <input
                            v-model="line.amount"
                            type="number"
                            min="0"
                            :step="payLines.length > 1 ? '0.01' : '1'"
                            :disabled="laneLocked"
                            :placeholder="payLines.length === 1 && line.method === 'cash' ? t('orders.tendered_short') : t('payments.amount')"
                            :title="payLines.length === 1 && line.method === 'cash' ? t('orders.tendered') : t('payments.amount')"
                            :aria-label="payLines.length === 1 && line.method === 'cash' ? t('orders.tendered') : t('payments.amount')"
                            class="h-6 w-[4.5rem] min-w-0 shrink-0 rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none placeholder:text-[11px] placeholder:text-muted/70 focus:border-accent disabled:opacity-50"
                        >
                        <input
                            v-if="methodType(line.method) !== 'cash'"
                            v-model="line.reference"
                            type="text"
                            :disabled="laneLocked"
                            :placeholder="needsReference(line.method) ? t('payments.ref_short') : t('payments.ref_optional')"
                            :aria-label="t('payments.trxid')"
                            class="h-6 w-full min-w-0 flex-1 rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none placeholder:text-[11px] placeholder:text-muted/70 focus:border-accent disabled:opacity-50"
                        >
                        <div v-else :ref="(element) => index === 0 && bindChips(element)" class="flex min-w-0 flex-1 items-center justify-end gap-1 overflow-hidden">
                            <button
                                v-for="amount in (payLines.length === 1 && methodType(line.method) === 'cash' ? cashChips : [])"
                                :key="amount"
                                type="button"
                                :disabled="laneLocked"
                                class="h-6 shrink-0 rounded-md border border-border bg-surface px-1.5 text-xs font-medium tabular-nums text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                                @click="quickAmount(amount)"
                            >
                                {{ amount === orderDue() ? t('orders.exact') : formatMoney(amount, { decimals: 0 }) }}
                            </button>
                        </div>
                        <button
                            v-if="index === 0"
                            type="button"
                            :disabled="laneLocked"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            :aria-label="t('payments.add_line')"
                            :title="t('payments.add_line')"
                            @click="addPayLine"
                        >
                            <AppIcon name="plus" size="11" />
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="laneLocked"
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-muted hover:text-danger disabled:cursor-not-allowed disabled:opacity-50"
                            :aria-label="t('payments.remove_line')"
                            :title="t('payments.remove_line')"
                            @click="removePayLine(index)"
                        >
                            <AppIcon name="xmark" size="11" />
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="grid grid-cols-6 gap-1">
                    <button
                        v-if="posActions.send_to_kitchen.enabled"
                        type="button"
                        :disabled="cart.length === 0 || !! latestOrder || discountBlocked"
                        class="flex h-7 items-center justify-center gap-1 rounded-md bg-primary text-[13px] font-semibold text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="primarySpanClass"
                        @click="submitOrder('send_to_kitchen')"
                    >
                        <AppIcon name="utensils" size="12" />
                        {{ editingOrder ? t('orders.update_order') : t('orders.send_kitchen') }}
                    </button>
                    <button
                        v-if="posActions.quick_save.enabled && payMethods.includes('cash')"
                        type="button"
                        :disabled="cart.length === 0 || !! latestOrder || discountBlocked"
                        class="flex h-7 items-center justify-center gap-1 rounded-md bg-accent text-[13px] font-semibold text-accent-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="primarySpanClass"
                        @click="submitOrder('quick_save')"
                    >
                        <AppIcon name="bolt" size="12" />
                        {{ t('orders.quick_save') }}
                    </button>
                    <button
                        v-if="posActions.confirm_payment.enabled"
                        type="button"
                        :disabled="! canConfirmPayment"
                        class="flex h-7 items-center justify-center gap-1 rounded-md bg-success text-[13px] font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="primarySpanClass"
                        @click="confirmPayment"
                    >
                        <AppIcon name="money-bill-wave" size="12" />
                        {{ confirmPaymentLabel }}
                    </button>
                    <button
                        type="button"
                        :disabled="cart.length === 0 || !! latestOrder"
                        class="col-span-2 flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-background text-xs font-medium text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="holdOrder"
                    >
                        <AppIcon name="clock" size="11" />
                        {{ t('orders.hold') }}
                    </button>
                    <button
                        type="button"
                        :disabled="! latestOrder && ! lastPayment"
                        class="col-span-2 flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-background text-xs font-medium text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="printCustomerCopy"
                    >
                        <AppIcon name="print" size="11" />
                        {{ t('orders.print_short') }}
                    </button>
                    <button
                        type="button"
                        :disabled="! lastPayment && ! latestOrder"
                        class="col-span-2 flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-background text-xs font-medium text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="startNewOrder"
                    >
                        <AppIcon name="plus" size="11" />
                        {{ t('orders.new_order') }}
                    </button>
                </div>
            </footer>

        </section>

        <Teleport to="body">
            <div v-if="variantPicker" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-3" @click.self="variantPicker = null">
                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('orders.choose_size')"
                    class="grid max-h-[min(32rem,calc(100vh-1.5rem))] w-full max-w-xl overflow-hidden rounded-xl border border-border bg-surface sm:grid-cols-[11rem_minmax(0,1fr)]"
                >
                    <div class="relative h-36 overflow-hidden bg-[#f6efe4] dark:bg-background sm:h-auto sm:min-h-full">
                        <img
                            v-if="itemPhotoSrc(variantPicker)"
                            :src="itemPhotoSrc(variantPicker)"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                            @error="markPhotoFailed(variantPicker.id)"
                        >
                        <div v-else class="flex h-full min-h-36 items-center justify-center">
                            <DishArt
                                :kind="dishKind(itemCategoryName(variantPicker))"
                                :seed="variantPicker.id"
                                class="h-32 w-32"
                            />
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col">
                        <div class="border-b border-border px-3 py-2">
                            <p class="text-xs font-medium text-muted">{{ t('orders.choose_size') }}</p>
                            <h2 class="truncate text-base font-semibold text-foreground">{{ localized(variantPicker, 'name') }}</h2>
                        </div>

                        <div class="thin-scrollbar flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto p-2">
                            <div
                                v-for="variant in sortedVariants(variantPicker)"
                                :key="variant.id"
                                class="flex items-center gap-2 rounded-lg border px-2 py-1 transition-colors"
                                :class="variantQuantity(variant) > 0 ? 'border-accent bg-accent/10' : 'border-border bg-background'"
                            >
                                <span :class="menuNoBadge">{{ variant.menu_no }}</span>
                                <button
                                    type="button"
                                    class="flex min-h-11 min-w-0 flex-1 flex-col items-start justify-center text-left disabled:opacity-50"
                                    :disabled="!!latestOrder"
                                    @click="selectVariant(variant)"
                                >
                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                        <AppIcon v-if="variantQuantity(variant) > 0" name="circle-check" size="12" class="text-success" />
                                        {{ localized(variant, 'name') }}
                                    </span>
                                    <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(variant.price) }}</span>
                                </button>
                                <div class="flex shrink-0 items-center overflow-hidden rounded-md border border-border bg-surface">
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center text-foreground disabled:opacity-40"
                                        :disabled="!!latestOrder || variantQuantity(variant) === 0"
                                        :aria-label="t('common.decrease')"
                                        @click="decreaseVariant(variant)"
                                    >
                                        <AppIcon name="minus" size="12" />
                                    </button>
                                    <span class="w-6 text-center text-sm font-semibold tabular-nums text-foreground">{{ variantQuantity(variant) }}</span>
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center bg-accent text-accent-foreground disabled:opacity-40"
                                        :disabled="!!latestOrder"
                                        :aria-label="t('common.increase')"
                                        @click="selectVariant(variant)"
                                    >
                                        <AppIcon name="plus" size="12" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-border px-3 py-2">
                            <p class="text-sm text-muted">
                                {{ pickerQuantity > 0 ? t('orders.sizes_added', { count: pickerQuantity }) : t('orders.choose_size') }}
                            </p>
                            <p class="text-base font-semibold tabular-nums text-foreground">{{ formatMoney(pickerTotal) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="optionsDialog" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-3" @click.self="optionsDialog = null">
                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('orders.customize')"
                    class="grid max-h-[min(36rem,calc(100vh-1.5rem))] w-full max-w-xl overflow-hidden rounded-xl border border-border bg-surface sm:grid-cols-[11rem_minmax(0,1fr)]"
                >
                    <div class="relative h-24 overflow-hidden bg-[#f6efe4] dark:bg-background sm:h-auto sm:min-h-full">
                        <img
                            v-if="itemPhotoSrc(optionsDialog.item)"
                            :src="itemPhotoSrc(optionsDialog.item)"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                            @error="markPhotoFailed(optionsDialog.item.id)"
                        >
                        <div v-else class="flex h-full min-h-24 items-center justify-center">
                            <DishArt
                                :kind="dishKind(itemCategoryName(optionsDialog.item))"
                                :seed="optionsDialog.item.id"
                                class="h-24 w-24 sm:h-32 sm:w-32"
                            />
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col">
                        <div class="flex items-start justify-between gap-2 border-b border-border px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-muted">{{ t('orders.customize') }}</p>
                                <h2 class="truncate text-base font-semibold text-foreground">{{ localized(optionsDialog.item, 'name') }}</h2>
                            </div>
                            <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted hover:text-foreground" :aria-label="t('common.cancel')" @click="optionsDialog = null">
                                <AppIcon name="xmark" size="14" />
                            </button>
                        </div>

                        <div class="thin-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-2">
                            <section v-if="itemHasVariants(optionsDialog.item)">
                                <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('orders.choose_size') }}</h3>
                                <div class="space-y-1.5" :role="optionsPicksVariants() ? 'group' : 'radiogroup'">
                                    <div
                                        v-for="variant in sortedVariants(optionsDialog.item)"
                                        :key="variant.id"
                                        class="flex items-center gap-2 rounded-lg border px-2 py-1 transition-colors"
                                        :class="(optionsPicksVariants() ? optionVariantQuantity(variant) > 0 : optionsDialog.variantId === variant.id) ? 'border-accent bg-accent/10' : 'border-border bg-background hover:border-accent/60'"
                                        :style="optionsPicksVariants() && optionsDialog.activeVariantId === variant.id ? { boxShadow: '0 0 0 1px var(--color-accent)' } : null"
                                    >
                                        <span :class="menuNoBadge">{{ variant.menu_no }}</span>
                                        <button
                                            type="button"
                                            :role="optionsPicksVariants() ? undefined : 'radio'"
                                            :aria-checked="optionsPicksVariants() ? undefined : optionsDialog.variantId === variant.id"
                                            class="flex min-h-11 min-w-0 flex-1 flex-col items-start justify-center text-left"
                                            @click="optionsPicksVariants() ? clickOptionVariant(variant) : (optionsDialog.variantId = variant.id)"
                                        >
                                            <span class="text-sm font-semibold text-foreground">{{ localized(variant, 'name') }}</span>
                                            <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(variant.price) }}</span>
                                        </button>
                                        <div v-if="optionsPicksVariants()" class="flex shrink-0 items-center overflow-hidden rounded-md border border-border bg-surface">
                                            <button type="button" class="flex h-9 w-9 items-center justify-center text-foreground disabled:opacity-40" :disabled="optionVariantQuantity(variant) === 0" :aria-label="t('common.decrease')" @click="setOptionVariant(variant, optionVariantQuantity(variant) - 1)">
                                                <AppIcon name="minus" size="12" />
                                            </button>
                                            <span class="w-6 text-center text-sm font-semibold tabular-nums text-foreground">{{ optionVariantQuantity(variant) }}</span>
                                            <button type="button" class="flex h-9 w-9 items-center justify-center bg-accent text-accent-foreground disabled:opacity-40" :disabled="optionVariantQuantity(variant) >= 999" :aria-label="t('common.increase')" @click="setOptionVariant(variant, optionVariantQuantity(variant) + 1)">
                                                <AppIcon name="plus" size="12" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">
                                    {{ optionsPicksVariants() && optionsActiveVariant ? t('orders.add_ons_for', { name: localized(optionsActiveVariant, 'name') }) : t('orders.add_ons') }}
                                </h3>
                                <div class="space-y-1.5">
                                    <div
                                        v-for="addOn in activeAddOns(optionsDialog.item)"
                                        :key="addOn.id"
                                        class="flex items-center gap-2 rounded-lg border px-2 py-1 transition-colors"
                                        :class="optionAddOnQty(addOn.id) > 0 ? 'border-accent bg-accent/10' : 'border-border bg-background'"
                                    >
                                        <span :class="menuNoBadge">{{ addOn.menu_no }}</span>
                                        <button type="button" class="flex min-h-10 min-w-0 flex-1 items-center justify-between gap-2 text-left" @click="setOptionAddOn(addOn.id, optionAddOnQty(addOn.id) + 1)">
                                            <span class="flex min-w-0 items-center gap-1.5 text-sm font-medium text-foreground">
                                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border" :class="optionAddOnQty(addOn.id) > 0 ? 'border-accent bg-accent text-accent-foreground' : 'border-border'">
                                                    <AppIcon v-if="optionAddOnQty(addOn.id) > 0" name="check" size="9" />
                                                </span>
                                                <span class="truncate">{{ localized(addOn, 'name') }}</span>
                                            </span>
                                            <span class="shrink-0 text-xs tabular-nums text-muted">+ {{ formatMoney(addOn.price) }}</span>
                                        </button>
                                        <div v-if="optionAddOnQty(addOn.id) > 0" class="flex shrink-0 items-center overflow-hidden rounded-md border border-border bg-surface">
                                            <button type="button" class="flex h-8 w-8 items-center justify-center text-foreground" :aria-label="t('common.decrease')" @click="setOptionAddOn(addOn.id, optionAddOnQty(addOn.id) - 1)">
                                                <AppIcon name="minus" size="11" />
                                            </button>
                                            <span class="w-5 text-center text-sm font-semibold tabular-nums">{{ optionAddOnQty(addOn.id) }}</span>
                                            <button type="button" class="flex h-8 w-8 items-center justify-center bg-accent text-accent-foreground disabled:opacity-40" :disabled="optionAddOnQty(addOn.id) >= 9" :aria-label="t('common.increase')" @click="setOptionAddOn(addOn.id, optionAddOnQty(addOn.id) + 1)">
                                                <AppIcon name="plus" size="11" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                            <div v-if="! optionsPicksVariants()" class="flex shrink-0 items-center overflow-hidden rounded-md border border-border bg-background">
                                <button type="button" class="flex h-9 w-9 items-center justify-center text-foreground disabled:opacity-40" :disabled="optionsDialog.quantity <= 1" :aria-label="t('common.decrease')" @click="optionsDialog.quantity = Math.max(1, optionsDialog.quantity - 1)">
                                    <AppIcon name="minus" size="12" />
                                </button>
                                <span class="w-8 text-center text-sm font-semibold tabular-nums">{{ optionsDialog.quantity }}</span>
                                <button type="button" class="flex h-9 w-9 items-center justify-center text-foreground" :aria-label="t('common.increase')" @click="optionsDialog.quantity = Math.min(999, optionsDialog.quantity + 1)">
                                    <AppIcon name="plus" size="12" />
                                </button>
                            </div>
                            <button type="button" class="flex h-9 min-w-0 flex-1 items-center justify-between gap-2 rounded-lg bg-accent px-3 text-sm font-semibold text-accent-foreground hover:opacity-90 disabled:opacity-50" :disabled="optionsPicksVariants() && optionsPickedVariants.length === 0" @click="confirmOptions">
                                <span>{{ optionsDialog.line ? t('orders.update_item') : t('orders.add_to_order') }}</span>
                                <span class="tabular-nums">{{ formatMoney(optionsTotal) }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="shortcutsOpen" class="fixed inset-0 z-[85] flex items-center justify-center bg-black/60 p-3" @click.self="shortcutsOpen = false">
                <div role="dialog" aria-modal="true" :aria-label="t('orders.shortcuts_title')" class="flex max-h-[min(36rem,calc(100vh-1.5rem))] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-border bg-surface">
                    <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-2.5">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
                            <AppIcon name="keyboard" size="14" class="text-muted" />
                            {{ t('orders.shortcuts_title') }}
                        </h2>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:text-foreground" :aria-label="t('common.close')" @click="shortcutsOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="thin-scrollbar min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-3">
                        <section v-for="group in shortcutGroups" :key="group.title">
                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ group.title }}</h3>
                            <dl class="divide-y divide-border rounded-lg border border-border">
                                <div v-for="item in group.items" :key="item.label" class="flex items-center justify-between gap-3 px-3 py-1.5">
                                    <dt class="text-[13px] text-foreground">{{ item.label }}</dt>
                                    <dd class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                                        <template v-for="(key, index) in item.keys" :key="key">
                                            <span v-if="index > 0 && key !== '...' && item.keys[index - 1] !== '...'" class="text-[11px] text-muted">{{ t('orders.sc_or') }}</span>
                                            <span v-if="key === '...'" class="text-xs text-muted">...</span>
                                            <kbd v-else class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[11px] font-medium text-foreground shadow-[0_1px_0_var(--color-border)]">{{ key }}</kbd>
                                        </template>
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <p class="border-t border-border px-4 py-2 text-xs text-muted">{{ t('orders.shortcuts_hint') }}</p>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="itemEditor" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4" @keydown.esc="itemEditor = null">
                <form class="w-[17.5rem] rounded-xl border border-border bg-surface p-3" @submit.prevent="applyItemEditor">
                    <div class="flex flex-col gap-2">
                        <div>
                            <h2 class="text-[13px] font-semibold text-foreground">{{ t('orders.edit_item') }}</h2>
                            <p class="truncate text-xs text-muted">{{ itemEditor.name }}</p>
                        </div>

                        <label class="grid grid-cols-[minmax(0,1fr)_4.5rem] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.custom_quantity') }}</span>
                            <input v-model.number="itemEditor.quantity" type="number" min="1" max="999" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>

                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.item_discount') }}</span>
                            <div class="flex items-center gap-1">
                                <button type="button" class="h-7 w-7 rounded-md border text-[13px] font-medium" :class="itemEditor.discountType !== 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="itemEditor.discountType = 'percent'">%</button>
                                <button type="button" class="h-7 w-7 rounded-md border text-[13px] font-medium" :class="itemEditor.discountType === 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="itemEditor.discountType = 'fixed'">৳</button>
                                <input v-model.number="itemEditor.discountValue" type="number" min="0" :aria-label="t('orders.item_discount')" class="h-7 w-14 rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                            </div>
                        </div>

                        <label class="grid grid-cols-[minmax(0,1fr)_4.5rem] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.custom_vat') }}</span>
                            <input v-model="itemEditor.vatPercent" type="number" min="0" max="100" step="0.01" :placeholder="String(Number(branch.vat_percent))" :aria-label="t('orders.custom_vat')" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>
                        <p class="text-xs text-muted">{{ t('orders.custom_vat_hint') }}</p>

                        <label class="grid grid-cols-[minmax(0,1fr)_4.5rem] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.other_charge') }}</span>
                            <input v-model.number="itemEditor.extraCharge" type="number" min="0" step="0.01" :aria-label="t('orders.other_charge')" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="h-7 rounded-md border border-border px-3 text-[13px] text-foreground hover:bg-background" @click="itemEditor = null">{{ t('common.cancel') }}</button>
                            <button type="submit" class="h-7 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground hover:opacity-90">{{ t('common.apply') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Running tables and orders: pick one to change its items -->
        <Teleport to="body">
            <div v-if="runningOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="runningOpen = false" @keydown.esc="runningOpen = false">
                <div class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl border border-border bg-surface" role="dialog" aria-modal="true" :aria-label="t('orders.running_orders')">
                    <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">{{ t('orders.running_orders') }}</h2>
                            <p class="text-xs text-muted">{{ t('orders.running_hint') }}</p>
                        </div>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('common.close')" @click="runningOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="thin-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                        <section v-for="group in tablesByArea" :key="group.area || 'tables'">
                            <h3 class="mb-1.5 text-xs font-semibold text-muted">{{ group.area || t('orders.table_short') }}</h3>
                            <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 md:grid-cols-4">
                                <PosTableTile v-for="table in group.tables" :key="table.id" :seats="table.seats" :tone="openOrderByTable.get(table.id) ? 'busy' : 'free'">
                                    <button
                                        type="button"
                                        :disabled="! openOrderByTable.get(table.id)"
                                        class="flex w-full flex-col items-center text-center disabled:cursor-default"
                                        :class="openOrderByTable.get(table.id) ? '' : 'opacity-60'"
                                        @click="editOrder(openOrderByTable.get(table.id))"
                                    >
                                        <span class="text-sm font-semibold leading-tight text-foreground">{{ table.name }}</span>
                                        <template v-if="openOrderByTable.get(table.id)">
                                            <span class="max-w-full truncate text-[11px] leading-tight text-muted">{{ openOrderByTable.get(table.id).invoice_no }}</span>
                                            <span class="text-xs font-semibold leading-tight tabular-nums text-foreground">{{ formatMoney(openOrderByTable.get(table.id).total) }}</span>
                                        </template>
                                        <span v-else class="text-[11px] leading-tight text-muted">{{ t('orders.table_free') }}</span>
                                    </button>
                                    <div v-if="openOrderByTable.get(table.id)" class="mt-1 flex items-center gap-1">
                                            <button type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-foreground hover:border-accent" :title="t('orders.print_invoice')" @click.stop="printOrderInvoice(openOrderByTable.get(table.id))">
                                                <AppIcon name="print" size="9" />
                                                {{ t('orders.print_short') }}
                                            </button>
                                            <button type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-success/40 bg-surface text-[11px] font-medium text-success hover:bg-success/10 disabled:opacity-50" :disabled="clearBusy" :title="t('orders.mark_paid')" @click.stop="markOrderPaid(openOrderByTable.get(table.id))">
                                                <AppIcon name="circle-check" size="9" />
                                                {{ t('orders.paid_short') }}
                                            </button>
                                            <button v-if="canVoid" type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-danger/40 bg-surface text-[11px] font-medium text-danger hover:bg-danger/10 disabled:opacity-50" :disabled="clearBusy" @click.stop="clearTable(table)">
                                                <AppIcon name="trash" size="9" />
                                                {{ t('orders.clear_short') }}
                                            </button>
                                        </div>
                                </PosTableTile>
                            </div>
                        </section>

                        <section v-if="openOrdersWithoutTable.length">
                            <h3 class="mb-1.5 text-xs font-semibold text-muted">{{ t('orders.no_table') }}</h3>
                            <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 md:grid-cols-4">
                                <div v-for="order in openOrdersWithoutTable" :key="order.id" class="rounded-xl border border-warning/60 bg-warning/10 px-2 py-1.5">
                                    <button type="button" class="flex w-full flex-col items-center text-center" @click="editOrder(order)">
                                        <span class="max-w-full truncate text-sm font-semibold leading-tight text-foreground">{{ order.invoice_no }}</span>
                                        <span class="text-[11px] leading-tight text-muted">{{ t(`orders.type.${order.order_type}`) }}</span>
                                        <span class="text-xs font-semibold leading-tight tabular-nums text-foreground">{{ formatMoney(order.total) }}</span>
                                    </button>
                                    <div class="mt-1 flex items-center gap-1">
                                            <button type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-foreground hover:border-accent" :title="t('orders.print_invoice')" @click.stop="printOrderInvoice(order)">
                                                <AppIcon name="print" size="9" />
                                                {{ t('orders.print_short') }}
                                            </button>
                                            <button type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-success/40 bg-surface text-[11px] font-medium text-success hover:bg-success/10 disabled:opacity-50" :disabled="clearBusy" :title="t('orders.mark_paid')" @click.stop="markOrderPaid(order)">
                                                <AppIcon name="circle-check" size="9" />
                                                {{ t('orders.paid_short') }}
                                            </button>
                                            <button v-if="canVoid" type="button" class="inline-flex h-5 flex-1 items-center justify-center gap-0.5 rounded-md border border-danger/40 bg-surface text-[11px] font-medium text-danger hover:bg-danger/10 disabled:opacity-50" :disabled="clearBusy" @click.stop="clearRunningOrder(order)">
                                                <AppIcon name="trash" size="9" />
                                                {{ t('orders.clear_short') }}
                                            </button>
                                        </div>
                                </div>
                            </div>
                        </section>

                        <p v-if="! openOrders.length" class="py-4 text-center text-[13px] text-muted">{{ t('orders.no_running_orders') }}</p>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Table picker -->
        <Teleport to="body">
            <div v-if="tablePickerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="tablePickerOpen = false" @keydown.esc="tablePickerOpen = false">
                <div class="flex max-h-[85vh] w-full max-w-xl flex-col rounded-xl border border-border bg-surface" role="dialog" aria-modal="true" :aria-label="t('orders.select_table')">
                    <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">{{ t('orders.select_table') }}</h2>
                            <p class="text-xs text-muted">{{ t('orders.table_optional') }}</p>
                        </div>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('common.close')" @click="tablePickerOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="thin-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                        <button
                            type="button"
                            class="flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border text-sm font-medium"
                            :class="tableId === null ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                            @click="pickTable(null)"
                        >
                            <AppIcon name="bag-shopping" size="13" />
                            {{ t('orders.no_table') }}
                        </button>

                        <section v-for="group in tablesByArea" :key="group.area || 'tables'">
                            <h3 class="mb-1.5 text-xs font-semibold text-muted">{{ group.area || t('orders.table_short') }}</h3>
                            <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                                <PosTableTile v-for="table in group.tables" :key="table.id" :seats="table.seats" :tone="table.id === tableId ? 'selected' : table.status === 'occupied' ? 'blocked' : 'free'">
                                    <button
                                        type="button"
                                        :disabled="table.status === 'occupied' && table.id !== tableId"
                                        class="flex w-full flex-col items-center text-center disabled:cursor-not-allowed"
                                        @click="pickTable(table.id)"
                                    >
                                        <span class="text-sm font-semibold leading-tight" :class="table.status === 'occupied' && table.id !== tableId ? 'text-danger' : 'text-foreground'">{{ table.name }}</span>
                                        <span class="text-[11px] leading-tight text-muted">
                                            {{ table.status === 'occupied' ? t('orders.table_occupied') : t('orders.table_seats', { count: table.seats }) }}
                                        </span>
                                    </button>
                                    <button v-if="table.status === 'occupied' && table.id !== tableId && (canVoid || ! openOrderByTable.get(table.id))"
                                        type="button"
                                        class="absolute right-1 top-1 inline-flex h-5 items-center gap-0.5 rounded-md border border-danger/40 bg-surface px-1 text-[11px] font-medium text-danger hover:bg-danger/10 disabled:opacity-50"
                                        :disabled="clearBusy"
                                        @click.stop="clearTable(table)"
                                    >
                                        <AppIcon name="trash" size="9" />
                                        {{ t('orders.clear_short') }}
                                    </button>
                                </PosTableTile>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Shift dialog -->
        <Teleport to="body">
            <div v-if="shiftDialogOpen && ! openShift" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('orders.open_shift') }}</h2>
                    <p class="mt-0.5 text-sm text-muted">{{ t('orders.open_shift_hint') }}</p>

                    <div class="mt-3">
                        <FormField :label="t('orders.opening_cash')" required>
                            <MoneyInput v-model="openingCash" />
                        </FormField>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm text-foreground hover:bg-background" @click="shiftDialogOpen = false">{{ t('common.cancel') }}</button>
                        <button type="button" class="h-9 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:opacity-90" @click="startShift">{{ t('orders.open_shift') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="locked" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4" data-pos-lock>
            <form novalidate class="w-full max-w-sm rounded-xl border border-border bg-surface p-4" @submit.prevent="unlockPos">
                <h2 class="text-base font-semibold">{{ t('orders.pos_locked') }}</h2>
                <p class="mt-1 text-[13px] text-muted">{{ t('orders.pos_locked_hint') }}</p>
                <input v-model="pin" type="password" inputmode="numeric" autocomplete="off" class="mt-3 h-9 w-full rounded-md border border-border bg-background px-2 text-sm" :placeholder="t('common.manager_pin')">
                <p v-if="pinError" class="mt-1 text-xs text-danger">{{ pinError }}</p>
                <button type="submit" class="mt-3 h-9 w-full rounded-md bg-primary text-sm font-semibold text-primary-foreground">{{ t('orders.unlock') }}</button>
            </form>
        </div>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
