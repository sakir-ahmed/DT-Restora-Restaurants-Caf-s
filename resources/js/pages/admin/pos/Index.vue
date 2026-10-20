<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DishArt from '@/components/site/DishArt.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { dishKind, localized } from '@/lib/site';
import { cacheBootstrap, findOutbox, getCachedMenu, getDataVersion, getLocalShift, nextLocalInvoice, queueOutbox, rememberInvoiceCursor, rememberLocalShift, updateOutboxPayload } from '@/lib/offline';
import { notify } from '@/lib/notify';
import { printHtml, printUrl } from '@/lib/print';
import { localReceiptHtml } from '@/lib/receipt';

const props = defineProps({
    categories: { type: Array, required: true },
    tables: { type: Array, default: () => [] },
    branch: { type: Object, required: true },
    openShift: { type: Object, default: null },
    canOverrideDiscount: { type: Boolean, default: false },
    canVoid: { type: Boolean, default: false },
    posPinMinutes: { type: Number, default: 5 },
});

const confirm = useConfirm();
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
const heldOrders = ref([]);
const payMode = ref(savedTicket?.payMode ?? 'cash');
const tendered = ref(savedTicket?.tendered ?? '');
const blankSplitLines = () => [
    { method: 'cash', amount: '', reference: '' },
    { method: 'bkash', amount: '', reference: '' },
];
const splitLines = ref(blankSplitLines());
const walletTrxId = ref('');
const walletMethod = ref('bkash');
const shiftDialogOpen = ref(false);
const openingCash = ref('0.00');
const locked = ref(false);
const pin = ref('');
const pinError = ref('');
let idleTimer = null;
const lastPayment = ref(savedTicket?.lastPayment ?? null);

const TERMINAL = localStorage.getItem('pos-terminal') ?? 'T01';
const latestOrder = ref(savedTicket?.latestOrder ?? null);

watch(
    [cart, discount, orderType, tableId, customerMobile, tendered, payMode, latestOrder, lastPayment],
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
            tendered: tendered.value,
            payMode: payMode.value,
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

function addItem(item) {
    if (itemHasVariants(item)) {
        variantPicker.value = item;

        return;
    }

    pushLine(item, null);
}

function variantLine(variant) {
    const item = variantPicker.value;

    if (! item) {
        return null;
    }

    return cart.value.find((line) => line.item.id === item.id
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

    return cart.value.filter((line) => line.item.id === item.id && line.variant);
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

function pushLine(item, variant) {
    const existing = cart.value.find((line) => line.item.id === item.id
        && (line.variant?.id ?? null) === (variant?.id ?? null));

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({
            item,
            variant,
            quantity: 1,
            addOns: [],
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
    return `${line.item.id}-${line.variant?.id ?? 0}`;
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
    cart.value = [];
    discount.value = { type: 'percent', value: 0 };
    latestOrder.value = null;
    lastPayment.value = null;
    tendered.value = '';
    payMode.value = 'cash';
    walletTrxId.value = '';
    splitLines.value = blankSplitLines();
    itemEditor.value = null;
    sessionStorage.removeItem(TICKET_KEY);
}

async function submitOrder() {
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

    if (! navigator.onLine) {
        const invoiceNo = await nextLocalInvoice(branch.value.code, TERMINAL);
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

        return;
    }

    router.post(route('admin.orders.store'), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const order = page.props.flash?.order ?? null;

            if (order?.invoice_no) {
                const sequence = Number(String(order.invoice_no).split('-').pop());

                if (Number.isFinite(sequence)) {
                    rememberInvoiceCursor(branch.value.code, TERMINAL, sequence);
                }

                latestOrder.value = order;
            }

            notify.success(order?.invoice_no ? `${t('orders.sent_to_kitchen')} ${order.invoice_no}` : t('orders.sent_to_kitchen'));
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

async function payNow() {
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
        printHtml(localReceiptHtml({
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
        }));

        return;
    }

    if (! orderId) {
        notify.info(t('orders.send_before_print'));

        return;
    }

    printUrl(route('admin.orders.receipt', orderId));
}

// keyboard shortcuts: / focus search, F2 new order (clear), F8 hold
function onKeydown(event) {
    if (event.key === 'Escape') {
        if (variantPicker.value) {
            variantPicker.value = null;
        } else if (itemEditor.value) {
            itemEditor.value = null;
        }

        return;
    }

    if (variantPicker.value || itemEditor.value) {
        return;
    }

    if (event.key === '/' && document.activeElement?.tagName !== 'INPUT') {
        event.preventDefault();
        document.querySelector('[data-pos-search]')?.focus();
    } else if (event.key === 'F8') {
        event.preventDefault();
        holdOrder();
    }
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

onMounted(() => {
    reconcileCart();

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

function startSplit() {
    const remaining = Number(latestOrder.value?.total ?? total.value);
    splitLines.value = [
        { method: 'cash', amount: Math.ceil(remaining / 2), reference: '' },
        { method: 'bkash', amount: remaining - Math.ceil(remaining / 2), reference: '' },
    ];
    payMode.value = 'split';
}

function splitTotal() {
    return splitLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0);
}

function paySplit() {
    const order = latestOrder.value;

    if (! order) {
        return;
    }

    if (! navigator.onLine || order.local) {
        queueOfflinePayment(order, {
            method: 'split',
            lines: splitLines.value.map((line) => ({
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
        });

        return;
    }

    router.post(route('admin.payments.split', order.id), {
        lines: splitLines.value.map((line) => ({
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
            method: walletMethod.value,
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
        });

        return;
    }

    router.post(route('admin.payments.manual', order.id), {
        method: walletMethod.value,
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
        },
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('common.error_occurred')),
    });
}

// One confirm button serves all three payment lanes.
function confirmPayment() {
    if (payMode.value === 'cash') {
        payNow();
    } else if (payMode.value === 'split') {
        paySplit();
    } else {
        payWalletManual();
    }
}

function chooseLane(mode) {
    if (lastPayment.value) {
        return;
    }

    if (mode === 'split') {
        startSplit();
    } else {
        payMode.value = mode;
    }
}

const canConfirmPayment = computed(() => !! latestOrder.value && ! lastPayment.value && (payMode.value !== 'wallet' || !! walletTrxId.value));
const confirmPaymentLabel = computed(() => (payMode.value === 'wallet' ? t('payments.record_manual') : t('orders.confirm_payment')));
const laneLocked = computed(() => !! lastPayment.value);
const discountAllTotal = computed(() => itemDiscountTotal.value + billDiscountAmount.value);
const changeShown = computed(() => (lastPayment.value ? Number(lastPayment.value.change_due ?? 0) : changeDue.value));

const changeDue = computed(() => Math.max(0, Number(tendered.value || 0) - (latestOrder.value ? Number(latestOrder.value.total) : total.value)));
</script>

<template>
    <div class="flex h-[calc(100vh-3.5rem-var(--demo-banner-h))] flex-col gap-2 overflow-hidden p-2 lg:flex-row">
        <!-- Left: categories + items -->
        <section class="flex min-w-0 flex-1 flex-col gap-2">
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
            </div>

            <div class="thin-scrollbar grid flex-1 grid-cols-2 content-start gap-2 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="item in filteredItems"
                    :key="item.id"
                    type="button"
                    class="flex min-h-16 items-center gap-2 rounded-lg border border-border bg-surface p-2 text-left transition-colors hover:border-accent"
                    @click="addItem(item)"
                >
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
                                <AppIcon v-if="itemHasVariants(item)" name="layer-group" size="12" class="text-muted" />
                                <AppIcon v-if="item.is_vegetarian" name="circle-check" size="12" class="text-success" />
                            </span>
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <!-- Right: cart -->
        <section class="flex h-full min-h-0 w-full shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-surface lg:w-[26rem]">
            <!-- Selected items -->
            <div class="thin-scrollbar min-h-0 flex-1 overflow-y-auto px-2">
                <p v-if="cart.length === 0" class="py-6 text-center text-[13px] text-muted">{{ t('orders.cart_empty') }}</p>

                <div v-for="line in cart" :key="lineKey(line)" class="border-b border-border py-1.5 last:border-b-0">
                    <div class="flex items-center gap-1">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-medium text-foreground">{{ localized(line.item, 'name') }}</p>
                            <p v-if="line.variant" class="truncate text-xs text-muted">{{ localized(line.variant, 'name') }}</p>
                            <p v-if="(line.addOns ?? []).length" class="truncate text-xs text-muted">{{ line.addOns.map((addOn) => addOn.name).join(', ') }}</p>
                        </div>
                        <div class="flex items-center">
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-foreground disabled:opacity-50" :disabled="!!latestOrder" :aria-label="t('common.decrease')" @click.stop="setQuantity(line, line.quantity - 1)">
                                <AppIcon name="minus" size="12" />
                            </button>
                            <span class="w-9 text-center text-[13px] font-semibold tabular-nums text-foreground">{{ line.quantity }}</span>
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-foreground disabled:opacity-50" :disabled="!!latestOrder || line.quantity >= 999" :aria-label="t('common.increase')" @click.stop="setQuantity(line, line.quantity + 1)">
                                <AppIcon name="plus" size="12" />
                            </button>
                        </div>
                        <span class="w-16 shrink-0 text-right text-[13px] font-semibold tabular-nums text-foreground">{{ formatMoney(lineNet(line)) }}</span>
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
                    <select
                        v-model="tableId"
                        :disabled="orderType !== 'dine_in'"
                        :aria-label="t('orders.select_table')"
                        class="h-7 w-24 shrink-0 rounded-md border border-border bg-background px-1 text-[13px] text-foreground outline-none focus:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option :value="null" disabled>{{ t('orders.table_short') }}</option>
                        <optgroup v-for="group in tablesByArea" :key="group.area || 'tables'" :label="group.area || t('orders.table_short')">
                            <option v-for="table in group.tables" :key="table.id" :value="table.id" :disabled="table.status === 'occupied'">
                                {{ table.name }}{{ table.status === 'occupied' ? ` (${t('orders.table_occupied')})` : '' }}
                            </option>
                        </optgroup>
                    </select>
                    <div class="min-w-0 flex-1">
                        <MobileInput v-model="customerMobile" :placeholder="t('orders.customer_mobile')" />
                    </div>
                </div>

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
                    <span data-pos-invoice class="shrink-0 font-mono text-[11px] text-muted" :title="latestOrder?.invoice_no">{{ latestOrder?.invoice_no ?? '-' }}</span>
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

                <!-- Payment lanes: choose a lane to enable its fields -->
                <div class="space-y-1" role="radiogroup" :aria-label="t('orders.payment_lane')">
                    <!-- Cash -->
                    <div class="flex items-center gap-1 rounded-md border px-1.5 py-0.5" :class="payMode === 'cash' ? 'border-accent bg-accent/10' : 'border-border bg-background'">
                        <button type="button" role="radio" :aria-checked="payMode === 'cash'" :disabled="laneLocked" class="flex h-6 w-16 shrink-0 items-center gap-1.5 text-[13px] font-medium text-foreground disabled:opacity-60" @click="chooseLane('cash')">
                            <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border" :class="payMode === 'cash' ? 'border-accent' : 'border-border'"><span class="h-1.5 w-1.5 rounded-full" :class="payMode === 'cash' ? 'bg-accent' : 'bg-transparent'" /></span>
                            {{ t('payment_method.cash') }}
                        </button>
                        <input v-model="tendered" type="number" min="0" step="1" :disabled="payMode !== 'cash' || laneLocked" :placeholder="t('orders.tendered_short')" :title="t('orders.tendered')" :aria-label="t('orders.tendered')" class="h-6 w-20 min-w-0 rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none placeholder:text-[11px] placeholder:text-muted/70 focus:border-accent disabled:opacity-50">
                        <div class="ml-auto flex gap-1">
                            <button
                                v-for="amount in [total, 100, 500, 1000]"
                                :key="amount"
                                type="button"
                                :disabled="payMode !== 'cash' || laneLocked"
                                class="h-6 rounded-md border border-border bg-surface px-1.5 text-xs font-medium tabular-nums text-foreground hover:border-accent disabled:cursor-not-allowed disabled:opacity-50"
                                @click="quickAmount(amount)"
                            >
                                {{ amount === total ? t('orders.exact') : formatMoney(amount, { decimals: 0 }) }}
                            </button>
                        </div>
                    </div>

                    <!-- Split -->
                    <div class="flex items-center gap-1 rounded-md border px-1.5 py-0.5" :class="payMode === 'split' ? 'border-accent bg-accent/10' : 'border-border bg-background'">
                        <button type="button" role="radio" :aria-checked="payMode === 'split'" :disabled="laneLocked" class="flex h-6 w-16 shrink-0 items-center gap-1.5 text-[13px] font-medium text-foreground disabled:opacity-60" @click="chooseLane('split')">
                            <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border" :class="payMode === 'split' ? 'border-accent' : 'border-border'"><span class="h-1.5 w-1.5 rounded-full" :class="payMode === 'split' ? 'bg-accent' : 'bg-transparent'" /></span>
                            {{ t('orders.split_short') }}
                        </button>
                        <div v-for="(splitLine, index) in splitLines" :key="index" class="flex min-w-0 flex-1 gap-1">
                            <select v-model="splitLine.method" :disabled="payMode !== 'split' || laneLocked" :aria-label="t('payment_method.split')" class="h-6 w-[4.25rem] shrink-0 rounded-md border border-border bg-surface px-0.5 text-xs text-foreground outline-none focus:border-accent disabled:opacity-50">
                                <option value="cash">{{ t('payment_method.cash') }}</option>
                                <option value="bkash">{{ t('payment_method.bkash') }}</option>
                                <option value="nagad">{{ t('payment_method.nagad') }}</option>
                                <option value="card">{{ t('payment_method.card') }}</option>
                            </select>
                            <input v-model="splitLine.amount" type="number" min="0" :disabled="payMode !== 'split' || laneLocked" :aria-label="t('payments.amount')" class="h-6 w-full min-w-0 rounded-md border border-border bg-surface px-1.5 text-right text-[13px] tabular-nums text-foreground outline-none focus:border-accent disabled:opacity-50">
                        </div>
                    </div>

                    <!-- Wallet -->
                    <div class="flex items-center gap-1 rounded-md border px-1.5 py-0.5" :class="payMode === 'wallet' ? 'border-accent bg-accent/10' : 'border-border bg-background'">
                        <button type="button" role="radio" :aria-checked="payMode === 'wallet'" :disabled="laneLocked" class="flex h-6 w-16 shrink-0 items-center gap-1.5 text-[13px] font-medium text-foreground disabled:opacity-60" @click="chooseLane('wallet')">
                            <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border" :class="payMode === 'wallet' ? 'border-accent' : 'border-border'"><span class="h-1.5 w-1.5 rounded-full" :class="payMode === 'wallet' ? 'bg-accent' : 'bg-transparent'" /></span>
                            {{ t('payments.wallet') }}
                        </button>
                        <select v-model="walletMethod" :disabled="payMode !== 'wallet' || laneLocked" :aria-label="t('payments.wallet')" class="h-6 w-[4.25rem] shrink-0 rounded-md border border-border bg-surface px-0.5 text-xs text-foreground outline-none focus:border-accent disabled:opacity-50">
                            <option value="bkash">{{ t('payment_method.bkash') }}</option>
                            <option value="nagad">{{ t('payment_method.nagad') }}</option>
                        </select>
                        <input v-model="walletTrxId" type="text" :disabled="payMode !== 'wallet' || laneLocked" :placeholder="t('payments.trxid')" :aria-label="t('payments.trxid')" class="h-6 w-full min-w-0 flex-1 rounded-md border border-border bg-surface px-1.5 text-[13px] text-foreground outline-none placeholder:text-[11px] placeholder:text-muted/70 focus:border-accent disabled:opacity-50">
                    </div>
                </div>

                <!-- Actions -->
                <div class="grid grid-cols-6 gap-1">
                    <button
                        type="button"
                        :disabled="cart.length === 0 || !! latestOrder || discountBlocked || (orderType === 'dine_in' && ! tableId)"
                        class="col-span-3 flex h-7 items-center justify-center gap-1 rounded-md bg-primary text-[13px] font-semibold text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submitOrder"
                    >
                        <AppIcon name="utensils" size="12" />
                        {{ t('orders.send_kitchen') }}
                    </button>
                    <button
                        type="button"
                        :disabled="! canConfirmPayment"
                        class="col-span-3 flex h-7 items-center justify-center gap-1 rounded-md bg-success text-[13px] font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
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
