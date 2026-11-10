<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ModalStepper from '@/components/ui-kit/ModalStepper.vue';
import DishPlaceholder from '@/components/site/DishPlaceholder.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PosTableTile from '@/components/admin/PosTableTile.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import { useConfirm, useConfirmState } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { dishKind, localized } from '@/lib/site';
import { cacheBootstrap, findOutbox, getCachedMenu, getDataVersion, getDeviceId, getLocalShift, nextLocalInvoice, queueOutbox, rememberInvoiceCursor, rememberLocalShift, updateOutboxPayload } from '@/lib/offline';
import { useIncomingOrders } from '@/lib/incomingOrders';
import { notify } from '@/lib/notify';
import { parseInvoiceSequence } from '@/lib/invoice';
import { printHtml, printSequence, printUrl } from '@/lib/print';
import { playSound } from '@/lib/sounds';
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
    posLockEnabled: { type: Boolean, default: false },
    stockBook: { type: Object, default: () => ({ allow_negative: true, recipes: {}, stock: {} }) },
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
const customerName = ref(savedTicket?.customerName ?? '');
const cart = ref(savedTicket?.cart ?? []);
const discount = ref(savedTicket?.discount ?? { type: 'percent', value: 0 });
const itemEditor = ref(null);
const variantPicker = ref(null);
const optionsDialog = ref(null);
// Held orders live in this browser (per branch), so they survive a page change or a reload.
const HELD_KEY = `pos-held-orders-${props.branch.id}`;

function readHeldOrders() {
    try {
        const stored = JSON.parse(localStorage.getItem(HELD_KEY) ?? '[]');

        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
}

const heldOrders = ref(readHeldOrders());

watch(heldOrders, (orders) => {
    try {
        if (orders.length === 0) {
            localStorage.removeItem(HELD_KEY);
        } else {
            localStorage.setItem(HELD_KEY, JSON.stringify(orders));
        }
    } catch {
        // Storage can be full or blocked; held orders then last until the page changes.
    }
}, { deep: true });

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
const heldListOpen = ref(false);
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
    [cart, discount, orderType, tableId, customerMobile, customerName, payLines, latestOrder, lastPayment],
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
            customerName: customerName.value,
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

// The last two parts of an invoice number are enough to tell two bills apart at a glance.
function shortInvoice(invoiceNo) {
    return `#${String(invoiceNo ?? '').split('-').slice(-2).join('-')}`;
}

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
            if (data.force || data.reason_code) {
                playSound('order_cancelled');
            }

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

    router.post(route('admin.orders.pay', order.id), { tendered: order.balance ?? order.total }, {
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
    if (! orderEditEnabled.value) {
        notify.error(t('orders.edit_disabled'));

        return;
    }

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
    customerName.value = '';
    discount.value = order.discount_type ? { type: order.discount_type, value: Number(order.discount_value) } : { type: 'percent', value: 0 };
    tablePickerOpen.value = false;

    // Booking money is already on this bill, so the items stay as sent and only the balance is paid.
    if (Number(order.advance) > 0) {
        latestOrder.value = { id: order.id, invoice_no: order.invoice_no, total: order.total, balance: order.balance, advance: order.advance };
        notify.info(t('orders.advance_paid'), `${formatMoney(order.advance)} / ${t('orders.balance_due')} ${formatMoney(order.balance)}`, { silent: true });

        return;
    }

    editingOrder.value = order;
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
        playSound('order_cancelled');
        startNewOrder();
    }
}

function cancelEditing() {
    startNewOrder();
}

const hasTicketData = computed(() => cart.value.length > 0 || tableId.value !== null || !! customerMobile.value || !! customerName.value || !! editingOrder.value || !! latestOrder.value || !! lastPayment.value);

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
    send_to_kitchen: { enabled: true, exact_amount: false, print_invoice: false, print_kitchen: false, instant_print: false, clear: false },
    confirm_payment: { enabled: true, exact_amount: false, print_invoice: false, print_kitchen: false, instant_print: false, clear: false },
    quick_save: { enabled: false, exact_amount: true, print_invoice: true, print_kitchen: false, instant_print: true, clear: true },
    due_sale: { enabled: true, exact_amount: false, print_invoice: false, print_kitchen: false, instant_print: false, clear: true },
};
const posActions = computed(() => {
    const configured = branch.value.pos_settings?.actions ?? {};

    return Object.fromEntries(Object.entries(DEFAULT_ACTIONS).map(([key, defaults]) => [key, { ...defaults, ...(configured[key] ?? {}) }]));
});
const reviewBeforeSave = computed(() => Boolean(branch.value.pos_settings?.review_before_save));
const orderEditEnabled = computed(() => branch.value.pos_settings?.order_edit_enabled !== false);
const orderDeleteEnabled = computed(() => branch.value.pos_settings?.order_delete_enabled !== false);
// Bill review: the modal shows the cart and amounts before a new invoice is saved
const billReview = ref(null);
// Same numbers the payment lane shows: what the cashier typed as received, and the change from it.
// Quick save takes the typed cash when it covers the bill, otherwise the exact total.
function receivedFor(mode) {
    const due = total.value;
    const typed = payMode.value === 'cash' ? Number(tendered.value || 0) : payLinesTotal();

    if (mode === 'quick_save') {
        return payMode.value === 'cash' && typed >= due ? typed : due;
    }

    return typed;
}
const reviewAmounts = computed(() => {
    const due = total.value;
    const received = receivedFor(billReview.value?.mode);

    return { due, received, change: Math.max(0, received - due) };
});
const invoiceLayout = computed(() => branch.value.pos_settings?.invoice_layout ?? 'classic');
const invoicePaper = computed(() => branch.value.pos_settings?.invoice_paper ?? '80mm');
const primarySpanClass = computed(() => {
    const visible = ['send_to_kitchen', 'confirm_payment', 'quick_save'].filter((key) => posActions.value[key].enabled).length;

    return { 1: 'col-span-6', 2: 'col-span-3', 3: 'col-span-2' }[visible] ?? 'col-span-6';
});
// Due, Print and New order share the second row; the Due button can be switched off per branch.
const secondaryRowSpanClass = computed(() => (posActions.value.due_sale.enabled ? 'col-span-2' : 'col-span-3'));
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

/**
 * How many of an item the ticket can still hold before an ingredient runs out. The server checks
 * again on save; this only warns at selection time. Revising a sent order is left to the server.
 */
function fitStock(item, desired, exceptLine = null) {
    const book = props.stockBook;
    const recipe = book.recipes?.[item.id] ?? [];

    if (book.allow_negative || editingOrder.value || recipe.length === 0) {
        return { quantity: desired, blocker: null };
    }

    let allowed = desired;
    let blocker = null;

    recipe.forEach((component) => {
        if (component.qty <= 0) {
            return;
        }

        const used = cart.value.reduce((sum, line) => {
            const usage = line === exceptLine ? undefined : (book.recipes?.[line.item.id] ?? []).find((entry) => entry.ingredient_id === component.ingredient_id);

            return sum + (usage ? usage.qty * line.quantity : 0);
        }, 0);
        const ingredient = book.stock?.[component.ingredient_id];
        const fits = Math.max(0, Math.floor(((ingredient?.qty ?? 0) - used + 1e-9) / component.qty));

        if (fits < allowed) {
            allowed = fits;
            blocker = ingredient ? localized(ingredient, 'name') : String(component.ingredient_id);
        }
    });

    return { quantity: allowed, blocker };
}

/** The quantity that fits; an item already above stock is never reduced here, only stopped from growing. */
function clampToStock(item, desired, line = null) {
    const fit = fitStock(item, desired, line);
    const result = Math.min(desired, Math.max(fit.quantity, line?.quantity ?? 0));

    if (result < desired && fit.blocker) {
        notify.error(t('inventory.insufficient_stock', { ingredient: fit.blocker }));
    }

    return result;
}

function pushLine(item, variant, addOns = [], quantity = 1) {
    quantity = clampToStock(item, quantity);

    if (quantity <= 0) {
        return;
    }

    playSound('item_added');

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

    // An item without sizes that is already on the ticket opens as that line, so its quantity shows.
    if (! line && ! itemHasVariants(item)) {
        const own = cart.value.filter((entry) => entry.item.id === item.id);

        line = own.length === 1 ? own[0] : null;
    }

    const variants = sortedVariants(item);
    const chosen = line?.variant ?? variants.find((variant) => variant.is_default) ?? variants[0] ?? null;

    const dialog = {
        item,
        line,
        variantId: chosen?.id ?? null,
        variantQuantities: {},
        variantAddOns: {},
        managedLines: {},
        activeVariantId: null,
        quantity: line?.quantity ?? 0,
        addOns: Object.fromEntries((line?.addOns ?? []).map((addOn) => [addOn.id, addOn.qty])),
    };

    // Sizes already on the ticket start with their current quantity and add-ons, so the dialog edits them.
    // A size with several lines (different add-ons) is left alone and stays additive.
    if (! line && variants.length > 0) {
        variants.forEach((variant) => {
            const lines = cart.value.filter((entry) => entry.item.id === item.id && entry.variant?.id === variant.id);

            if (lines.length !== 1) {
                return;
            }

            dialog.managedLines[variant.id] = lines[0];
            dialog.variantQuantities[variant.id] = lines[0].quantity;
            dialog.variantAddOns[variant.id] = Object.fromEntries(lines[0].addOns.map((addOn) => [addOn.id, addOn.qty]));
            dialog.activeVariantId ??= variant.id;
        });
    }

    optionsDialog.value = dialog;
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

function optionsHasManagedLines() {
    return Object.keys(optionsDialog.value?.managedLines ?? {}).length > 0;
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

        // Picking an add-on for a dish that is not on the ticket yet adds one of the dish.
        if (clamped > 0 && dialog.quantity === 0) {
            dialog.quantity = 1;
        }

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
        const picked = optionsPickedVariants.value;
        const managed = dialog.managedLines ?? {};

        if (picked.length === 0 && ! optionsHasManagedLines()) {
            return;
        }

        // A size that was on the ticket and is now at zero is taken off.
        const pickedIds = new Set(picked.map((entry) => entry.variant.id));
        const removed = Object.entries(managed).filter(([id]) => ! pickedIds.has(Number(id))).map(([, managedLine]) => managedLine);

        cart.value = cart.value.filter((entry) => ! removed.includes(entry));

        picked.forEach((entry) => {
            const managedLine = managed[entry.variant.id];

            if (! managedLine) {
                pushLine(dialog.item, entry.variant, entry.addOns, entry.quantity);

                return;
            }

            const duplicate = cart.value.find((other) => other !== managedLine
                && other.item.id === dialog.item.id
                && other.variant?.id === entry.variant.id
                && addOnSignature(other.addOns) === addOnSignature(entry.addOns));

            if (duplicate) {
                duplicate.quantity = Math.min(999, clampToStock(dialog.item, duplicate.quantity + entry.quantity, duplicate));
                cart.value = cart.value.filter((other) => other !== managedLine);
            } else {
                managedLine.addOns = entry.addOns;
                managedLine.quantity = clampToStock(dialog.item, entry.quantity, managedLine);
            }
        });

        optionsDialog.value = null;

        return;
    }

    const quantity = Math.min(999, Math.max(0, Math.round(Number(dialog.quantity) || 0)));

    // Taking the quantity to zero takes the item off the ticket; a new item with zero adds nothing.
    if (quantity === 0) {
        if (dialog.line) {
            const line = dialog.line;

            cart.value = cart.value.filter((entry) => entry !== line);
            optionsDialog.value = null;
        }

        return;
    }

    if (dialog.line) {
        const line = dialog.line;
        const duplicate = cart.value.find((entry) => entry !== line
            && entry.item.id === dialog.item.id
            && (entry.variant?.id ?? null) === (variant?.id ?? null)
            && addOnSignature(entry.addOns) === addOnSignature(addOns));

        if (duplicate) {
            duplicate.quantity = Math.min(999, clampToStock(dialog.item, duplicate.quantity + quantity, duplicate));
            cart.value = cart.value.filter((entry) => entry !== line);
        } else {
            line.variant = variant;
            line.addOns = addOns;
            line.quantity = clampToStock(dialog.item, quantity, line);
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

    line.quantity = Math.min(999, clampToStock(line.item, Math.max(1, Math.round(Number(draft.quantity) || 1)), line));
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
        line.quantity = Math.min(999, clampToStock(line.item, Math.round(quantity), line));
    }
}

function armIdleLock() {
    clearTimeout(idleTimer);

    // The idle lock is a setting and is off until an admin turns it on.
    if (locked.value || ! props.posLockEnabled) {
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
    customerName.value = '';
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

function confirmBillReview() {
    const mode = billReview.value?.mode ?? 'send_to_kitchen';

    billReview.value = null;
    submitOrder(mode, true);
}

async function submitOrder(mode = 'send_to_kitchen', reviewed = false) {
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
        customer_name: customerMobile.value ? (customerName.value.trim() || null) : null,
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

    if (reviewBeforeSave.value && ! reviewed && ! editingOrder.value && cart.value.length > 0) {
        billReview.value = { mode };

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
            device_id: await getDeviceId(),
        });

        latestOrder.value = {
            local: true,
            id: null,
            client_id: queued.client_id,
            invoice_no: invoiceNo,
            total: total.value,
        };
        notify.info(t('sync.queued'), null, { silent: true });

        if (mode !== 'quick_save') {
            playSound('order_created');
        }

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

            if (order?.invoice_no && Number(order.balance ?? 1) <= 0) {
                // The booking money covered the whole bill.
                lastPayment.value = { order_id: order.id, change_due: 0, invoice_no: order.invoice_no };
                playSound('order_paid');
                notify.success(t('orders.paid_by_advance'));
            } else if (order?.invoice_no) {
                if (mode !== 'quick_save') {
                    playSound('order_created');
                }

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
    if (mode === 'quick_due') {
        postDue(order, dueLinesPending);

        return;
    }

    if (mode === 'quick_save') {
        const due = Number(order.balance ?? order.total ?? total.value);
        const typed = payMode.value === 'cash' ? Number(tendered.value || 0) : 0;

        payLines.value = [{ method: 'cash', amount: (typed >= due ? typed : due).toFixed(2), reference: '' }];
        payNow('quick_save');

        return;
    }

    printAfter('send_to_kitchen', order);
}

function localInvoiceHtml(order) {
    return localReceiptHtml({
        layout: invoiceLayout.value,
        paper: invoicePaper.value,
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

// After a button finishes: print what the branch settings ask for (customer invoice, kitchen token or both)
// when instant print is on, then clear the ticket when clear is on. Local receipts are built before the clear.
function printAfter(action, order) {
    const flags = posActions.value[action];
    const orderId = order?.id ?? null;
    const documents = [];

    if (flags.instant_print && flags.print_invoice) {
        documents.push(orderId ? { url: route('admin.orders.receipt', orderId) } : { html: localInvoiceHtml(order) });
    }

    if (flags.instant_print && flags.print_kitchen) {
        documents.push(orderId ? { url: route('admin.orders.kitchen-token', orderId) } : { html: localKitchenHtml(order) });
    }

    if (flags.clear) {
        startNewOrder();
        mobileTab.value = 'menu';
    }

    printSequence(documents);
}

async function payNow(source = 'confirm_payment') {
    const order = pagePropsOrder();

    if (! order) {
        return;
    }

    const tenderedAmount = Number(tendered.value || 0);

    if (tenderedAmount < orderDue()) {
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
            change_due: Math.max(0, tenderedAmount - orderDue()),
            invoice_no: order.invoice_no,
        };
        notify.info(t('sync.queued'));
        playSound('order_paid');
        printAfter(source, order);

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
            playSound('order_paid');
        printAfter(source, order);
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
        customerName: customerName.value,
        at: new Date().toISOString(),
    });

    startNewOrder();
    playSound('order_held');
    notify.info(t('orders.held'), null, { silent: true });
}

function resumeOrder(held) {
    cart.value = held.cart;
    orderType.value = held.orderType;
    tableId.value = held.tableId;
    customerMobile.value = held.customerMobile;
    customerName.value = held.customerName ?? '';
    heldOrders.value = heldOrders.value.filter((entry) => entry.id !== held.id);
}

function resumeHeldFromList(held) {
    resumeOrder(held);
    heldListOpen.value = false;
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

        // IndexedDB cannot store Vue reactive proxies, so hand it plain copies.
        await cacheBootstrap({
            categories: JSON.parse(JSON.stringify(toRaw(categories.value))),
            tables: JSON.parse(JSON.stringify(toRaw(tables.value))),
            branch: JSON.parse(JSON.stringify(toRaw(branch.value))),
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
    window.addEventListener('invoice-renumbered', onInvoiceRenumbered);
    armIdleLock();
    refreshOfflineCache();
});

// After a sync the server may have issued another number because a sale from another
// terminal already had the one this device printed. Show the number that now counts.
function onInvoiceRenumbered(event) {
    const { client_id: clientId, invoice_no: invoiceNo } = event.detail ?? {};

    if (latestOrder.value?.client_id === clientId) {
        latestOrder.value = { ...latestOrder.value, invoice_no: invoiceNo };
    }

    notify.info(t('sync.invoice_renumbered', { invoice: invoiceNo }));
}

onBeforeUnmount(() => {
    window.removeEventListener('invoice-renumbered', onInvoiceRenumbered);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('pointerdown', onActivity);
    window.removeEventListener('keydown', onActivity);
    clearTimeout(idleTimer);
});

function quickAmount(amount) {
    tendered.value = String(amount);
}

// What is left to collect: the bill less any booking money already taken off it.
function orderDue() {
    return Number(latestOrder.value?.balance ?? latestOrder.value?.total ?? total.value);
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
            playSound('order_paid');
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
            playSound('order_paid');
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
            amount: orderDue(),
            reference: walletTrxId.value,
        }).then(() => {
            lastPayment.value = {
                order_id: order.id ?? null,
                client_id: order.client_id ?? null,
                change_due: 0,
                invoice_no: order.invoice_no,
            };
            notify.info(t('sync.queued'));
            playSound('order_paid');
            printAfter('confirm_payment', order);
        });

        return;
    }

    router.post(route('admin.payments.manual', order.id), {
        method: payMethod.value,
        amount: orderDue(),
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
            playSound('order_paid');
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
            amount: orderDue(),
            reference: payLines.value[0].reference || null,
        }).then(() => {
            lastPayment.value = {
                order_id: order.id ?? null,
                client_id: order.client_id ?? null,
                change_due: 0,
                invoice_no: order.invoice_no,
            };
            notify.info(t('sync.queued'));
            playSound('order_paid');
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
            playSound('order_paid');
            printAfter('confirm_payment', order);
        },
        onError: (errors) => notify.error(String(Object.values(errors)[0] ?? t('common.error_occurred'))),
    });
}

// Past customers matching what is typed in the mobile or name box. Picking one fills both boxes.
const customerSuggestions = ref([]);
const suggestOpen = ref(false);
let suggestTimer = null;
let suggestSeq = 0;

function closeSuggestions(event) {
    if (! event.currentTarget.contains(event.relatedTarget)) {
        suggestOpen.value = false;
    }
}

function pickCustomer(match) {
    customerMobile.value = match.mobile;
    customerName.value = match.name ?? '';
    customerSuggestions.value = [];
    suggestOpen.value = false;
}

function searchCustomers(term) {
    clearTimeout(suggestTimer);

    if (term.length < 2 || ! navigator.onLine || (latestOrder.value && ! dueDialog.value)) {
        customerSuggestions.value = [];

        return;
    }

    suggestTimer = setTimeout(async () => {
        const seq = ++suggestSeq;

        try {
            const response = await fetch(`${route('admin.pos.customers')}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const matches = response.ok ? await response.json() : [];

            if (seq === suggestSeq) {
                customerSuggestions.value = matches.filter((match) => match.mobile !== customerMobile.value || (match.name ?? '') !== customerName.value);
            }
        } catch {
            customerSuggestions.value = [];
        }
    }, 250);
}

watch(customerMobile, (value) => searchCustomers(value ?? ''));
watch(customerName, (value) => {
    if (! customerMobile.value) {
        searchCustomers((value ?? '').trim());
    }
});

// A sale on due: whatever is typed as received is taken now, the rest stays owed by the customer.
function dueLines() {
    return payLines.value
        .filter((line) => Number(line.amount) > 0)
        .map((line) => ({ method: line.method, amount: Number(line.amount).toFixed(2), reference: line.reference || null }));
}

const canCompleteWithDue = computed(() => !! latestOrder.value
    && ! lastPayment.value
    && payLinesTotal() < orderDue() - 0.005
    && ! payLines.value.some((line) => methodType(line.method) === 'wallet'));

// The due button works straight from the cart (quick save on due) or on an order already sent.
const canQuickDue = computed(() => canCompleteWithDue.value || (canSendOrder() && ! lastPayment.value));

// The dialog asks for the customer when the ticket has none, then saves the sale on due.
const dueDialog = ref(null);
let dueLinesPending = [];

// Money taken now on a fresh ticket: typed cash, or the payment rows, only while it stays below the bill.
function dueReceivedLines() {
    if (latestOrder.value) {
        return dueLines();
    }

    if (payMode.value === 'cash') {
        const typed = Number(tendered.value || 0);

        return typed > 0 && typed < total.value ? [{ method: 'cash', amount: typed.toFixed(2), reference: null }] : [];
    }

    return payLinesTotal() < total.value - 0.005 && ! payLines.value.some((line) => methodType(line.method) === 'wallet') ? dueLines() : [];
}

const dueDialogAmounts = computed(() => {
    const due = orderDue();
    const received = dueReceivedLines().reduce((sum, line) => sum + Number(line.amount), 0);

    return { due, received, balance: Math.max(0, due - received) };
});

function openDueDialog() {
    if (! canQuickDue.value) {
        return;
    }

    if (! navigator.onLine || latestOrder.value?.local) {
        notify.error(t('sync.needs_internet'));

        return;
    }

    if (! latestOrder.value && ! openShift.value) {
        openShiftDialog();
        notify.info(t('orders.shift_needed'));

        return;
    }

    dueDialog.value = {};
    suggestOpen.value = true;
}

function closeDueDialog() {
    dueDialog.value = null;
    suggestOpen.value = false;
    customerSuggestions.value = [];
}

function confirmDue() {
    if (! customerMobile.value) {
        notify.error(t('orders.due_needs_customer'));

        return;
    }

    dueLinesPending = dueReceivedLines();
    closeDueDialog();

    if (latestOrder.value) {
        postDue(latestOrder.value, dueLinesPending);

        return;
    }

    submitOrder('quick_due', true);
}

function postDue(order, lines) {
    router.post(route('admin.orders.due', order.id), {
        lines,
        customer_mobile: customerMobile.value || null,
        customer_name: customerName.value.trim() || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            lastPayment.value = page.props.flash?.payment ?? { order_id: order.id, change_due: 0 };
            notify.success(String(page.props.flash?.success ?? t('orders.due_collected')));
            playSound('order_paid');
            printAfter('due_sale', order);
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
        tendered.value = orderDue().toFixed(2);
    }
});

const changeDue = computed(() => Math.max(0, Number(tendered.value || 0) - orderDue()));
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
                    <span
                        v-if="selectedQuantity[item.id]"
                        class="pointer-events-none absolute bottom-1 left-1 z-10 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold leading-none tabular-nums text-accent-foreground ring-2 ring-surface"
                        :title="t('orders.in_order')"
                    >
                        <span class="sr-only">{{ t('orders.in_order') }}: </span>{{ selectedQuantity[item.id] }}
                    </span>
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
                <div class="flex h-7 items-center gap-1 text-xs">
                    <div class="inline-flex h-7 shrink-0 items-stretch overflow-hidden rounded-md border border-border bg-background" role="group" :aria-label="t('orders.held_orders')">
                        <button
                            type="button"
                            :disabled="cart.length === 0 || !! latestOrder"
                            class="inline-flex items-center gap-1 bg-warning/15 px-2 text-[13px] font-medium text-foreground hover:bg-warning/30 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-muted disabled:opacity-50"
                            :title="t('orders.hold')"
                            :aria-label="t('orders.hold')"
                            @click="holdOrder"
                        >
                            <AppIcon name="plus" size="12" class="text-warning" />
                            {{ t('orders.hold') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 border-l border-border px-2.5 text-[13px] font-semibold text-foreground hover:bg-surface"
                            :title="t('orders.held_orders')"
                            :aria-label="t('orders.held_orders')"
                            @click="heldListOpen = true"
                        >
                            <AppIcon name="clock" size="12" class="text-warning" />
                            <span
                                class="inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                                :class="heldOrders.length ? 'bg-warning text-black' : 'bg-border text-muted'"
                            >{{ heldOrders.length }}</span>
                        </button>
                    </div>
                    <form class="flex h-7 min-w-0 flex-1 items-stretch overflow-hidden rounded-md border border-border bg-background focus-within:border-accent" @submit.prevent="addByMenuNumber">
                        <button type="button" class="flex w-7 shrink-0 items-center justify-center border-r border-border text-foreground hover:bg-surface disabled:opacity-40" :disabled="!!latestOrder || ! menuNumberInput" :aria-label="t('common.decrease')" :title="t('common.decrease')" @click="removeByMenuNumber">
                            <AppIcon name="minus" size="12" />
                        </button>
                        <input
                            v-model="menuNumberInput"
                            data-pos-menu-number
                            type="text" inputmode="numeric" v-numeric
                            min="1"
                            :placeholder="t('orders.menu_no_placeholder')"
                            :aria-label="t('orders.menu_no_placeholder')"
                            :disabled="!!latestOrder"
                            @keydown.enter.shift.prevent="removeByMenuNumber"
                            class="w-full min-w-0 border-0 bg-transparent px-2 text-center text-[13px] tabular-nums text-foreground outline-none [appearance:textfield] disabled:opacity-50 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        >
                        <button type="submit" class="flex w-7 shrink-0 items-center justify-center border-l border-border bg-accent text-accent-foreground hover:opacity-90 disabled:opacity-40" :disabled="!!latestOrder || ! menuNumberInput" :aria-label="t('orders.menu_no_add')" :title="t('orders.menu_no_add')">
                            <AppIcon name="plus" size="12" />
                        </button>
                    </form>
                    <button type="button" :disabled="! hasTicketData" class="inline-flex h-7 shrink-0 items-center justify-center gap-1 rounded-md border border-danger/40 px-2 text-[13px] font-medium bg-danger/10 text-danger hover:bg-danger hover:text-white disabled:cursor-not-allowed disabled:border-border disabled:bg-background disabled:text-muted disabled:opacity-50 disabled:hover:bg-background disabled:hover:text-muted" :title="t('orders.clear_all')" :aria-label="t('orders.clear_all')" @click="clearAll">
                        <AppIcon name="trash" size="12" />
                        {{ t('orders.clear_short') }}
                    </button>
                    <span v-if="latestOrder" data-pos-invoice class="sr-only" :title="latestOrder.invoice_no">{{ latestOrder.invoice_no }}</span>
                </div>

                <!-- Order type and shift -->
                <div class="flex items-center gap-1">
                    <div class="flex flex-1 gap-1" role="radiogroup" :aria-label="t('orders.type_label')">
                        <template v-for="type in ['dine_in', 'takeaway']" :key="type">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="orderType === type"
                                class="inline-flex h-7 flex-1 items-center justify-center rounded-md border px-2 text-[13px] font-medium"
                                :class="orderType === type ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                                @click="orderType = type"
                            >
                                {{ t(`orders.type.${type}`) }}
                            </button>
                            <template v-if="type === 'dine_in'">
                                <button
                                    type="button"
                                    :disabled="orderType !== 'dine_in' && ! openOrders.length"
                                    :title="t('orders.select_table')"
                                    :aria-label="t('orders.select_table')"
                                    class="inline-flex h-7 max-w-28 shrink-0 items-center gap-1.5 rounded-md border px-2 text-[13px] font-medium outline-none focus-visible:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="selectedTable ? 'border-accent bg-accent/15 font-semibold text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                                    @click="tablePickerOpen = true"
                                >
                                    <AppIcon name="table-cells-large" size="12" class="shrink-0" />
                                    <span class="truncate">{{ selectedTable ? selectedTable.name : t('orders.tables_label') }}</span>
                                    <span v-if="openOrders.length" class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground">{{ openOrders.length }}</span>
                                </button>
                            </template>
                        </template>
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

                <!-- Customer mobile and optional name; matches from past customers fill both -->
                <div class="relative flex items-center gap-1" @focusin="suggestOpen = true" @focusout="closeSuggestions" @keydown.esc="suggestOpen = false">
                    <div class="min-w-0 flex-1">
                        <MobileInput v-model="customerMobile" :placeholder="t('orders.customer_mobile')" />
                    </div>
                    <input
                        v-model="customerName"
                        type="text"
                        maxlength="100"
                        :placeholder="t('orders.customer_name')"
                        :aria-label="t('orders.customer_name')"
                        autocomplete="off"
                        class="h-7 min-w-0 flex-1 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none placeholder:text-muted/60 focus:border-accent focus:ring-2 focus:ring-accent/40"
                    >
                    <ul v-if="suggestOpen && customerSuggestions.length" class="thin-scrollbar absolute inset-x-0 bottom-full z-30 mb-1 max-h-48 overflow-y-auto rounded-md border border-border bg-surface py-1 shadow-lg" role="listbox">
                        <li v-for="match in customerSuggestions" :key="match.id" role="option">
                            <button type="button" class="flex w-full items-center justify-between gap-2 px-2 py-1 text-left text-[13px] hover:bg-background" @mousedown.prevent="pickCustomer(match)">
                                <span class="min-w-0 truncate font-medium text-foreground">{{ match.name || t('orders.walk_in_customer') }}</span>
                                <span class="shrink-0 tabular-nums text-muted">{{ match.mobile }}</span>
                            </button>
                        </li>
                    </ul>
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

                <!-- Booking money already taken off this bill -->
                <div v-if="Number(latestOrder?.advance) > 0 && ! lastPayment" class="flex items-center justify-between rounded-md border border-accent/50 bg-accent/10 px-2.5 py-1 text-[13px] text-foreground">
                    <span>{{ t('orders.advance_paid') }} <span class="tabular-nums">-{{ formatMoney(latestOrder.advance) }}</span></span>
                    <span class="font-semibold">{{ t('orders.balance_due') }} <span class="tabular-nums">{{ formatMoney(orderDue()) }}</span></span>
                </div>

                <!-- Total and change -->
                <div class="flex items-stretch gap-1">
                    <div
                        class="flex h-9 shrink-0 items-center gap-1 rounded-md border bg-background px-1.5 focus-within:border-accent"
                        :class="discountBlocked ? 'border-danger' : 'border-border'"
                        role="group"
                        :aria-label="t('orders.bill_discount')"
                        :title="discountBlocked ? t('orders.discount_limit_hint') : undefined"
                    >
                        <span class="text-xs text-muted">{{ t('orders.bill_discount') }}</span>
                        <div class="flex h-7 items-stretch overflow-hidden rounded border border-border bg-surface">
                            <button type="button" class="w-8 shrink-0 text-base font-semibold disabled:opacity-50" :disabled="!! latestOrder" :class="discount.type !== 'fixed' ? 'bg-accent text-accent-foreground' : 'text-muted hover:text-foreground'" @click="discount.type = 'percent'">%</button>
                            <button type="button" class="w-8 shrink-0 border-l border-border text-base font-semibold disabled:opacity-50" :disabled="!! latestOrder" :class="discount.type === 'fixed' ? 'bg-accent text-accent-foreground' : 'text-muted hover:text-foreground'" @click="discount.type = 'fixed'">৳</button>
                        </div>
                        <input
                            v-model.number="discount.value"
                            :disabled="!! latestOrder"
                            type="text" inputmode="decimal" v-numeric min="0"
                            :aria-label="t('orders.bill_discount')"
                            class="w-10 min-w-0 border-0 bg-transparent text-right text-base font-bold tabular-nums text-foreground outline-none disabled:opacity-60"
                            :class="discountBlocked ? 'text-danger' : ''"
                        >
                    </div>
                    <div class="flex h-9 min-w-0 flex-1 items-center justify-between gap-1 rounded-md bg-primary px-2 text-primary-foreground">
                        <span class="text-xs opacity-80">{{ t('receipt.grand_total') }}</span>
                        <span class="text-lg font-bold tabular-nums">{{ formatMoney(total) }}</span>
                    </div>
                    <div class="flex h-9 shrink-0 items-center justify-between gap-1.5 rounded-md bg-success/10 px-2">
                        <span class="text-xs text-muted">{{ t('orders.change_due') }}</span>
                        <span class="text-base font-bold tabular-nums" :class="changeShown > 0 ? 'text-success' : 'text-muted'">{{ formatMoney(changeShown) }}</span>
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
                            type="text" inputmode="decimal" v-numeric
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
                        v-if="posActions.due_sale.enabled"
                        type="button"
                        :disabled="! canQuickDue"
                        :title="t('orders.due_sale_title')"
                        class="col-span-2 flex h-7 items-center justify-center gap-1 rounded-md border border-warning/50 bg-warning/10 text-xs font-semibold text-foreground hover:bg-warning/25 disabled:cursor-not-allowed disabled:border-border disabled:bg-background disabled:text-muted disabled:opacity-50"
                        @click="openDueDialog"
                    >
                        <AppIcon name="hand-holding-dollar" size="11" />
                        {{ t('orders.due_sale') }}
                    </button>
                    <button
                        type="button"
                        :disabled="! latestOrder && ! lastPayment"
                        :class="secondaryRowSpanClass"
                        class="flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-background text-xs font-medium text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="printCustomerCopy"
                    >
                        <AppIcon name="print" size="11" />
                        {{ t('orders.print_short') }}
                    </button>
                    <button
                        type="button"
                        :disabled="! lastPayment && ! latestOrder"
                        :class="secondaryRowSpanClass"
                        class="flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-background text-xs font-medium text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
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
                            <DishPlaceholder
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
                                    <span class="text-sm font-semibold text-foreground">{{ localized(variant, 'name') }}</span>
                                    <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(variant.price) }}</span>
                                </button>
                                <ModalStepper
                                    :value="variantQuantity(variant)"
                                    :can-decrease="! latestOrder && variantQuantity(variant) > 0"
                                    :can-increase="! latestOrder"
                                    @decrease="decreaseVariant(variant)"
                                    @increase="selectVariant(variant)"
                                />
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
                            <DishPlaceholder
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
                                        <ModalStepper
                                            v-if="optionsPicksVariants()"
                                            :value="optionVariantQuantity(variant)"
                                            :can-decrease="optionVariantQuantity(variant) > 0"
                                            :can-increase="optionVariantQuantity(variant) < 999"
                                            @decrease="setOptionVariant(variant, optionVariantQuantity(variant) - 1)"
                                            @increase="setOptionVariant(variant, optionVariantQuantity(variant) + 1)"
                                        />
                                    </div>
                                </div>
                            </section>

                            <section v-if="! optionsPicksVariants()">
                                <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('orders.custom_quantity') }}</h3>
                                <div
                                    class="flex items-center gap-2 rounded-lg border px-2 py-1 transition-colors"
                                    :class="optionsDialog.quantity > 0 ? 'border-accent bg-accent/10' : 'border-border bg-background'"
                                >
                                    <span :class="menuNoBadge">{{ optionsDialog.item.menu_no }}</span>
                                    <button type="button" class="flex min-h-11 min-w-0 flex-1 flex-col items-start justify-center text-left" @click="optionsDialog.quantity = Math.min(999, optionsDialog.quantity + 1)">
                                        <span class="truncate text-sm font-semibold text-foreground">{{ localized(optionsDialog.item, 'name') }}</span>
                                        <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(optionsVariant?.price ?? optionsDialog.item.base_price) }}</span>
                                    </button>
                                    <ModalStepper
                                        :value="optionsDialog.quantity"
                                        :can-decrease="optionsDialog.quantity > 0"
                                        :can-increase="optionsDialog.quantity < 999"
                                        @decrease="optionsDialog.quantity = Math.max(0, optionsDialog.quantity - 1)"
                                        @increase="optionsDialog.quantity = Math.min(999, optionsDialog.quantity + 1)"
                                    />
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
                                                <span class="truncate">{{ localized(addOn, 'name') }}</span>
                                            </span>
                                            <span class="shrink-0 text-xs tabular-nums text-muted">+ {{ formatMoney(addOn.price) }}</span>
                                        </button>
                                        <ModalStepper
                                            :value="optionAddOnQty(addOn.id)"
                                            :can-decrease="optionAddOnQty(addOn.id) > 0"
                                            :can-increase="optionAddOnQty(addOn.id) < 9"
                                            @decrease="setOptionAddOn(addOn.id, optionAddOnQty(addOn.id) - 1)"
                                            @increase="setOptionAddOn(addOn.id, optionAddOnQty(addOn.id) + 1)"
                                        />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="flex items-center gap-2 border-t border-border px-3 py-2">
                            <button type="button" class="flex h-9 min-w-0 flex-1 items-center justify-between gap-2 rounded-lg bg-accent px-3 text-sm font-semibold text-accent-foreground hover:opacity-90 disabled:opacity-50" :disabled="(optionsPicksVariants() && optionsPickedVariants.length === 0 && ! optionsHasManagedLines()) || (! optionsPicksVariants() && optionsDialog.quantity === 0 && ! optionsDialog.line)" @click="confirmOptions">
                                <span>{{ ! optionsPicksVariants() && optionsDialog.quantity === 0 && optionsDialog.line ? t('common.remove') : (optionsDialog.line || optionsHasManagedLines() ? t('orders.update_item') : t('orders.add_to_order')) }}</span>
                                <span class="tabular-nums">{{ formatMoney(optionsTotal) }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="heldListOpen" class="fixed inset-0 z-[85] flex items-center justify-center bg-black/60 p-3" @click.self="heldListOpen = false">
                <div role="dialog" aria-modal="true" :aria-label="t('orders.held_orders')" class="flex max-h-[min(32rem,calc(100vh-1.5rem))] w-full max-w-md flex-col overflow-hidden rounded-xl border border-border bg-surface">
                    <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-2.5">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
                            <AppIcon name="clock" size="14" class="text-warning" />
                            {{ t('orders.held_orders') }} ({{ heldOrders.length }})
                        </h2>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md text-muted hover:text-foreground" :aria-label="t('common.close')" @click="heldListOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>
                    <div class="thin-scrollbar min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                        <p v-if="heldOrders.length === 0" class="py-6 text-center text-sm text-muted">{{ t('orders.held_empty') }}</p>
                        <div v-for="held in heldOrders" :key="held.id" class="flex items-center gap-2 rounded-lg border border-border bg-background p-2">
                            <button type="button" class="min-w-0 flex-1 text-left" @click="resumeHeldFromList(held)">
                                <span class="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    {{ t('orders.held_items', { count: held.cart.reduce((sum, line) => sum + line.quantity, 0) }) }}
                                    <span class="text-xs font-normal text-muted">{{ new Date(held.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </span>
                                <span class="block truncate text-xs text-muted">{{ held.cart.map((line) => `${line.quantity} x ${line.item.name}`).join(', ') }}</span>
                            </button>
                            <button type="button" class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md bg-accent px-3 text-sm font-semibold text-accent-foreground hover:opacity-90" @click="resumeHeldFromList(held)">
                                {{ t('orders.resume') }}
                            </button>
                            <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-border text-muted hover:border-danger hover:text-danger" :aria-label="t('common.remove')" @click="discardHeld(held)">
                                <AppIcon name="trash" size="12" />
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
                            <input v-model.number="itemEditor.quantity" type="text" inputmode="decimal" v-numeric min="1" max="999" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>

                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.item_discount') }}</span>
                            <div class="flex items-center gap-1">
                                <button type="button" class="h-7 w-7 rounded-md border text-[13px] font-medium" :class="itemEditor.discountType !== 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="itemEditor.discountType = 'percent'">%</button>
                                <button type="button" class="h-7 w-7 rounded-md border text-[13px] font-medium" :class="itemEditor.discountType === 'fixed' ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted'" @click="itemEditor.discountType = 'fixed'">৳</button>
                                <input v-model.number="itemEditor.discountValue" type="text" inputmode="decimal" v-numeric min="0" :aria-label="t('orders.item_discount')" class="h-7 w-14 rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                            </div>
                        </div>

                        <label class="grid grid-cols-[minmax(0,1fr)_4.5rem] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.custom_vat') }}</span>
                            <input v-model="itemEditor.vatPercent" type="text" inputmode="decimal" v-numeric min="0" max="100" step="0.01" :placeholder="String(Number(branch.vat_percent))" :aria-label="t('orders.custom_vat')" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>
                        <p class="text-xs text-muted">{{ t('orders.custom_vat_hint') }}</p>

                        <label class="grid grid-cols-[minmax(0,1fr)_4.5rem] items-center gap-2">
                            <span class="text-[13px] text-foreground">{{ t('orders.other_charge') }}</span>
                            <input v-model.number="itemEditor.extraCharge" type="text" inputmode="decimal" v-numeric min="0" step="0.01" :aria-label="t('orders.other_charge')" class="h-7 w-full rounded-md border border-border bg-background px-2 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent">
                        </label>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="h-7 rounded-md border border-border px-3 text-[13px] text-foreground hover:bg-background" @click="itemEditor = null">{{ t('common.cancel') }}</button>
                            <button type="submit" class="h-7 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground hover:opacity-90">{{ t('common.apply') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Table picker: free tables to select, busy tables to reopen their order -->
        <Teleport to="body">
            <div v-if="tablePickerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="tablePickerOpen = false" @keydown.esc="tablePickerOpen = false">
                <div class="flex max-h-[88vh] w-full max-w-xl flex-col rounded-xl border border-border bg-surface" role="dialog" aria-modal="true" :aria-label="t('orders.select_table')">
                    <div class="flex items-start justify-between gap-2 border-b border-border px-4 py-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-foreground">{{ t('orders.select_table') }}</h2>
                            <p class="mt-0.5 text-xs text-muted">{{ t('orders.table_picker_hint') }}</p>
                        </div>
                        <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('common.close')" @click="tablePickerOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-border px-4 py-2 text-xs text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-border" />{{ t('orders.table_free') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-warning/70" /><span class="capitalize">{{ t('orders.table_occupied') }}</span></span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-accent" />{{ t('orders.table_selected') }}</span>
                    </div>

                    <div class="thin-scrollbar min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                        <button
                            type="button"
                            :disabled="orderType !== 'dine_in'"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-lg border text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                            :class="tableId === null ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                            @click="pickTable(null)"
                        >
                            <AppIcon name="bag-shopping" size="14" />
                            {{ t('orders.no_table') }}
                        </button>

                        <section v-for="group in tablesByArea" :key="group.area || 'tables'">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ group.area || t('orders.table_short') }}</h3>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <PosTableTile v-for="table in group.tables" :key="table.id" :seats="table.seats" :tone="table.id === tableId ? 'selected' : openOrderByTable.get(table.id) ? 'busy' : table.status === 'occupied' ? 'blocked' : 'free'">
                                    <button
                                        type="button"
                                        :disabled="openOrderByTable.get(table.id) ? false : (table.status === 'occupied' && table.id !== tableId) || orderType !== 'dine_in'"
                                        class="flex w-full flex-col items-center gap-0.5 py-1 text-center disabled:cursor-not-allowed"
                                        @click="openOrderByTable.get(table.id) ? editOrder(openOrderByTable.get(table.id)) : pickTable(table.id)"
                                    >
                                        <span class="text-base font-semibold leading-tight" :class="table.status === 'occupied' && ! openOrderByTable.get(table.id) && table.id !== tableId ? 'text-danger' : 'text-foreground'">{{ table.name }}</span>
                                        <template v-if="openOrderByTable.get(table.id)">
                                            <span class="text-sm font-bold leading-tight tabular-nums text-foreground">{{ formatMoney(openOrderByTable.get(table.id).total) }}</span>
                                            <span class="font-mono text-[11px] leading-tight text-muted" :title="openOrderByTable.get(table.id).invoice_no">{{ shortInvoice(openOrderByTable.get(table.id).invoice_no) }}</span>
                                        </template>
                                        <template v-else-if="table.status === 'occupied'">
                                            <span class="text-xs font-semibold uppercase leading-tight tracking-wide text-danger">{{ t('orders.table_occupied') }}</span>
                                            <template v-if="table.last_order">
                                                <span class="text-sm font-bold leading-tight tabular-nums" :class="table.last_order.is_due || table.last_order.balance > 0 ? 'text-danger' : 'text-success'">
                                                    {{ table.last_order.is_due || table.last_order.balance > 0 ? t('orders.table_due', { amount: formatMoney(table.last_order.balance) }) : t('orders.table_paid', { amount: formatMoney(table.last_order.total) }) }}
                                                </span>
                                                <span v-if="table.last_order.is_due" class="text-[11px] leading-tight tabular-nums text-muted">{{ t('orders.table_paid_of', { paid: formatMoney(table.last_order.paid), total: formatMoney(table.last_order.total) }) }}</span>
                                                <span v-if="table.last_order.customer" class="max-w-full truncate text-[11px] leading-tight text-muted">{{ table.last_order.customer }}</span>
                                                <span class="font-mono text-[11px] leading-tight text-muted" :title="table.last_order.invoice_no">{{ shortInvoice(table.last_order.invoice_no) }} - {{ new Date(table.last_order.at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) }}</span>
                                            </template>
                                            <span v-else class="text-[11px] leading-tight text-muted">{{ t('orders.table_needs_clearing') }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="text-xs leading-tight text-muted">{{ t('orders.table_seats', { count: table.seats }) }}</span>
                                            <span v-if="table.booking" class="max-w-full truncate text-[11px] font-medium leading-tight text-primary" :title="table.booking.guest_name">
                                                {{ t('orders.table_booked', { time: new Date(table.booking.starts_at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) }) }}
                                            </span>
                                        </template>
                                    </button>

                                    <div v-if="openOrderByTable.get(table.id)" class="mt-1.5 grid gap-1" :class="canVoid && orderDeleteEnabled ? 'grid-cols-3' : 'grid-cols-2'">
                                        <button type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-surface text-xs font-medium text-foreground hover:border-accent" :title="t('orders.print_invoice')" @click.stop="printOrderInvoice(openOrderByTable.get(table.id))">
                                            <AppIcon name="print" size="11" />
                                            {{ t('orders.print_short') }}
                                        </button>
                                        <button type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-success/40 bg-success/10 text-xs font-medium text-success hover:bg-success/20 disabled:opacity-50" :disabled="clearBusy" :title="t('orders.mark_paid')" @click.stop="markOrderPaid(openOrderByTable.get(table.id))">
                                            <AppIcon name="circle-check" size="11" />
                                            {{ t('orders.paid_short') }}
                                        </button>
                                        <button v-if="canVoid && orderDeleteEnabled" type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-danger/40 bg-danger/10 text-xs font-medium text-danger hover:bg-danger/20 disabled:opacity-50" :disabled="clearBusy" @click.stop="clearTable(table)">
                                            <AppIcon name="trash" size="11" />
                                            {{ t('orders.clear_short') }}
                                        </button>
                                    </div>
                                    <button v-else-if="table.status === 'occupied' && table.id !== tableId" type="button" class="mt-1.5 inline-flex h-7 w-full items-center justify-center gap-1 rounded-md border border-danger/40 bg-danger/10 text-xs font-medium text-danger hover:bg-danger/20 disabled:opacity-50" :disabled="clearBusy" @click.stop="clearTable(table)">
                                        <AppIcon name="broom" size="11" />
                                        {{ t('orders.clear_table') }}
                                    </button>
                                </PosTableTile>
                            </div>
                        </section>

                        <section v-if="openOrdersWithoutTable.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ t('orders.running_orders_no_table') }}</h3>
                            <ul class="space-y-2">
                                <li v-for="order in openOrdersWithoutTable" :key="order.id" class="flex items-center gap-2 rounded-lg border border-warning/60 bg-warning/10 p-2">
                                    <button type="button" class="min-w-0 flex-1 text-left" @click="editOrder(order)">
                                        <span class="flex items-baseline gap-2">
                                            <span class="text-sm font-bold tabular-nums text-foreground">{{ formatMoney(order.total) }}</span>
                                            <span class="text-xs text-muted">{{ t(`orders.type.${order.order_type}`) }}</span>
                                        </span>
                                        <span class="block truncate font-mono text-[11px] text-muted" :title="order.invoice_no">{{ shortInvoice(order.invoice_no) }}</span>
                                    </button>
                                    <button type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-border bg-surface px-2 text-xs font-medium text-foreground hover:border-accent" :title="t('orders.print_invoice')" @click.stop="printOrderInvoice(order)">
                                        <AppIcon name="print" size="11" />
                                        {{ t('orders.print_short') }}
                                    </button>
                                    <button type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-success/40 bg-success/10 px-2 text-xs font-medium text-success hover:bg-success/20 disabled:opacity-50" :disabled="clearBusy" :title="t('orders.mark_paid')" @click.stop="markOrderPaid(order)">
                                        <AppIcon name="circle-check" size="11" />
                                        {{ t('orders.paid_short') }}
                                    </button>
                                    <button v-if="canVoid && orderDeleteEnabled" type="button" class="inline-flex h-7 items-center justify-center gap-1 rounded-md border border-danger/40 bg-danger/10 px-2 text-xs font-medium text-danger hover:bg-danger/20 disabled:opacity-50" :disabled="clearBusy" @click.stop="clearRunningOrder(order)">
                                        <AppIcon name="trash" size="11" />
                                        {{ t('orders.clear_short') }}
                                    </button>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Bill review before a new invoice is saved -->
        <Teleport to="body">
            <div v-if="billReview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" :aria-label="t('orders.review_title')" @keydown.esc="billReview = null">
                <div class="flex max-h-[92vh] w-full max-w-4xl flex-col rounded-xl border border-border bg-surface">
                    <div class="border-b border-border px-5 py-3">
                        <h2 class="text-lg font-semibold text-foreground">{{ t('orders.review_title') }}</h2>
                        <p class="text-[13px] text-muted">{{ t('orders.review_hint') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-border bg-background px-4 py-3">
                            <p class="text-sm font-medium text-muted">{{ t('orders.review_total') }}</p>
                            <p class="text-4xl font-bold tabular-nums text-foreground">{{ formatMoney(reviewAmounts.due) }}</p>
                        </div>
                        <div class="rounded-lg border border-accent bg-accent/10 px-4 py-3">
                            <p class="text-sm font-medium text-muted">{{ t('orders.review_received') }}</p>
                            <p class="text-4xl font-bold tabular-nums text-foreground">{{ formatMoney(reviewAmounts.received) }}</p>
                        </div>
                        <div class="rounded-lg border border-primary bg-primary/10 px-4 py-3">
                            <p class="text-sm font-medium text-muted">{{ t('orders.review_return') }}</p>
                            <p class="text-4xl font-bold tabular-nums text-primary">{{ formatMoney(reviewAmounts.change) }}</p>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5">
                        <table class="w-full text-[13px]">
                            <thead class="sticky top-0 bg-surface text-xs text-muted">
                                <tr>
                                    <th class="py-1.5 text-left font-medium">{{ t('orders.review_item') }}</th>
                                    <th class="w-24 py-1.5 text-right font-medium">{{ t('orders.review_price') }}</th>
                                    <th class="w-16 py-1.5 text-right font-medium">{{ t('orders.review_qty') }}</th>
                                    <th class="w-28 py-1.5 text-right font-medium">{{ t('orders.review_amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in cart" :key="lineKey(line)" class="border-t border-border align-top">
                                    <td class="py-1.5 pr-2">
                                        <p class="font-medium text-foreground">{{ localized(line.item, 'name') }}</p>
                                        <p v-if="line.variant" class="text-xs text-muted">{{ localized(line.variant, 'name') }}</p>
                                        <p v-if="(line.addOns ?? []).length" class="text-xs text-muted">{{ line.addOns.map((addOn) => (addOn.qty > 1 ? `${addOn.qty}x ` : '+ ') + addOn.name).join(', ') }}</p>
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums">{{ formatMoney(lineUnitPrice(line)) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ line.quantity }}</td>
                                    <td class="py-1.5 text-right font-semibold tabular-nums">{{ formatMoney(lineNet(line)) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <dl class="ml-auto mt-2 w-full max-w-xs space-y-0.5 border-t border-border py-2 text-[13px]">
                            <div class="flex justify-between"><dt class="text-muted">{{ t('receipt.subtotal') }}</dt><dd class="tabular-nums">{{ formatMoney(subtotal) }}</dd></div>
                            <div v-if="discountAllTotal > 0" class="flex justify-between"><dt class="text-muted">{{ t('orders.discount') }}</dt><dd class="tabular-nums text-danger">-{{ formatMoney(discountAllTotal) }}</dd></div>
                            <div v-if="serviceCharge > 0" class="flex justify-between"><dt class="text-muted">{{ t('receipt.service_charge') }}</dt><dd class="tabular-nums">{{ formatMoney(serviceCharge) }}</dd></div>
                            <div v-if="vatAmount > 0" class="flex justify-between"><dt class="text-muted">{{ t('receipt.vat') }}</dt><dd class="tabular-nums">{{ formatMoney(vatAmount) }}</dd></div>
                            <div class="flex justify-between border-t border-border pt-1 font-semibold"><dt>{{ t('orders.review_total') }}</dt><dd class="tabular-nums">{{ formatMoney(total) }}</dd></div>
                        </dl>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                        <button type="button" class="h-10 rounded-md border border-border px-4 text-sm text-foreground hover:bg-background" @click="billReview = null">{{ t('common.cancel') }}</button>
                        <button type="button" class="h-10 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground hover:opacity-90" @click="confirmBillReview">{{ t('orders.review_confirm') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Due sale: confirm, and ask for the customer when the ticket has none -->
        <Teleport to="body">
            <div v-if="dueDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" :aria-label="t('orders.due_sale_title')" @keydown.esc="closeDueDialog" @keydown.enter.prevent="confirmDue">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('orders.due_sale_title') }}</h2>
                    <p class="mt-0.5 text-sm text-muted">{{ t('orders.due_confirm_message') }}</p>

                    <dl class="mt-3 divide-y divide-border rounded-md border border-border bg-background text-sm">
                        <div class="flex justify-between px-3 py-1.5"><dt class="text-muted">{{ t('orders.review_total') }}</dt><dd class="font-medium tabular-nums">{{ formatMoney(dueDialogAmounts.due) }}</dd></div>
                        <div class="flex justify-between px-3 py-1.5"><dt class="text-muted">{{ t('orders.due_received') }}</dt><dd class="font-medium tabular-nums">{{ formatMoney(dueDialogAmounts.received) }}</dd></div>
                        <div class="flex justify-between px-3 py-1.5"><dt class="font-semibold">{{ t('orders.due_balance') }}</dt><dd class="font-bold tabular-nums text-danger">{{ formatMoney(dueDialogAmounts.balance) }}</dd></div>
                    </dl>

                    <div class="relative mt-3 flex items-center gap-1" @focusin="suggestOpen = true" @focusout="closeSuggestions">
                        <div class="min-w-0 flex-1">
                            <MobileInput v-model="customerMobile" :placeholder="t('orders.customer_mobile')" />
                        </div>
                        <input
                            v-model="customerName"
                            type="text"
                            maxlength="100"
                            :placeholder="t('orders.customer_name')"
                            :aria-label="t('orders.customer_name')"
                            autocomplete="off"
                            class="h-7 min-w-0 flex-1 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none placeholder:text-muted/60 focus:border-accent focus:ring-2 focus:ring-accent/40"
                        >
                        <ul v-if="suggestOpen && customerSuggestions.length" class="thin-scrollbar absolute inset-x-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-border bg-surface py-1 shadow-lg" role="listbox">
                            <li v-for="match in customerSuggestions" :key="match.id" role="option">
                                <button type="button" class="flex w-full items-center justify-between gap-2 px-2 py-1 text-left text-[13px] hover:bg-background" @mousedown.prevent="pickCustomer(match)">
                                    <span class="min-w-0 truncate font-medium text-foreground">{{ match.name || t('orders.walk_in_customer') }}</span>
                                    <span class="shrink-0 tabular-nums text-muted">{{ match.mobile }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm text-foreground hover:bg-background" @click="closeDueDialog">{{ t('common.cancel') }}</button>
                        <button type="button" :disabled="! customerMobile" class="h-9 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50" @click="confirmDue">{{ t('orders.due_sale') }}</button>
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
