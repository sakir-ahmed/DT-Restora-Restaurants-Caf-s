<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import RadioCards from '@/components/ui-kit/RadioCards.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    categories: { type: Array, required: true },
    tables: { type: Array, default: () => [] },
    branch: { type: Object, required: true },
    openShift: { type: Object, default: null },
    canOverrideDiscount: { type: Boolean, default: false },
    canVoid: { type: Boolean, default: false },
});

const confirm = useConfirm();

const activeCategory = ref(null);
const search = ref('');
const orderType = ref('takeaway');
const tableId = ref(null);
const customerMobile = ref('');
const cart = ref([]);
const discount = ref({ type: 'percent', value: 0 });
const heldOrders = ref([]);
const payDialogOpen = ref(false);
const payMode = ref('cash'); // cash | split | wallet
const tendered = ref('');
const splitLines = ref([]);
const walletTrxId = ref('');
const walletMethod = ref('bkash');
const shiftDialogOpen = ref(false);
const openingCash = ref('0.00');
const lastPayment = ref(null);
const discountDialogOpen = ref(false);

const TERMINAL = localStorage.getItem('pos-terminal') ?? 'T01';
const latestOrder = ref(null);

const allItems = computed(() => props.categories.flatMap((category) => category.items));

const filteredItems = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (needle) {
        return allItems.value.filter((item) => item.name.toLowerCase().includes(needle)
            || (item.name_bn ?? '').includes(search.value.trim()));
    }

    if (activeCategory.value === null) {
        return allItems.value;
    }

    return props.categories.find((category) => category.id === activeCategory.value)?.items ?? [];
});

function addItem(item) {
    const defaultVariant = item.variants?.find((variant) => variant.is_default) ?? item.variants?.[0] ?? null;

    const existing = cart.value.find((line) => line.item.id === item.id
        && (line.variant?.id ?? null) === (defaultVariant?.id ?? null));

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({
            item,
            variant: defaultVariant,
            quantity: 1,
            addOns: [],
        });
    }
}

function lineUnitPrice(line) {
    return Number(line.variant?.price ?? line.item.base_price);
}

function lineAddOnsTotal(line) {
    return line.addOns.reduce((sum, addOn) => sum + addOn.price * addOn.qty, 0);
}

function lineTotal(line) {
    return (lineUnitPrice(line) * line.quantity) + (lineAddOnsTotal(line) * line.quantity);
}

function toggleAddOn(line, addOn) {
    const existing = line.addOns.find((entry) => entry.id === addOn.id);

    if (existing) {
        line.addOns = line.addOns.filter((entry) => entry.id !== addOn.id);
    } else {
        line.addOns.push({ id: addOn.id, name: addOn.name, price: Number(addOn.price), qty: 1 });
    }
}

function hasAddOn(line, addOn) {
    return line.addOns.some((entry) => entry.id === addOn.id);
}

const subtotal = computed(() => cart.value.reduce((sum, line) => sum + lineTotal(line), 0));

const discountAmount = computed(() => {
    if (discount.value.value <= 0) {
        return 0;
    }

    const amount = discount.value.type === 'percent'
        ? subtotal.value * discount.value.value / 100
        : discount.value.value;

    return Math.min(amount, subtotal.value);
});

const serviceCharge = computed(() => ((subtotal.value - discountAmount.value) * Number(props.branch.service_charge_percent)) / 100);

const vatBase = computed(() => subtotal.value - discountAmount.value + serviceCharge.value);

const vatAmount = computed(() => {
    const rate = Number(props.branch.vat_percent);

    return props.branch.vat_inclusive
        ? vatBase.value - (vatBase.value * 100 / (100 + rate))
        : vatBase.value * rate / 100;
});

const rawTotal = computed(() => (props.branch.vat_inclusive ? vatBase.value : vatBase.value + vatAmount.value));

const total = computed(() => Math.round(rawTotal.value));

const rounding = computed(() => total.value - rawTotal.value);

const itemCount = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0));

function setQuantity(line, quantity) {
    if (quantity <= 0) {
        cart.value = cart.value.filter((entry) => entry !== line);
    } else {
        line.quantity = quantity;
    }
}

function submitOrder() {
    const payload = {
        order_type: orderType.value,
        restaurant_table_id: orderType.value === 'dine_in' ? tableId.value : null,
        customer_mobile: customerMobile.value || null,
        note: null,
        terminal: TERMINAL,
        items: cart.value.map((line) => ({
            menu_item_id: line.item.id,
            variant_id: line.variant?.id ?? null,
            quantity: line.quantity,
            add_ons: line.addOns.map((addOn) => ({ id: addOn.id, qty: addOn.qty })),
        })),
    };

    if (discount.value.value > 0) {
        payload.discount = { type: discount.value.type, value: discount.value.value };
    }

    router.post(route('admin.orders.store'), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const order = page.props.flash?.order ?? null;

            cart.value = [];
            discount.value = { type: 'percent', value: 0 };

            if (order) {
                latestOrder.value = order;
                lastPayment.value = null;
                payDialogOpen.value = true;
                payMode.value = 'cash';
                tendered.value = String(order.total);
                walletTrxId.value = '';
                walletMethod.value = 'bkash';
                splitLines.value = [];
            }
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            notify.error(String(first ?? t('common.error_occurred')));
        },
    });
}

function payNow() {
    const order = pagePropsOrder();

    if (! order) {
        return;
    }

    router.post(route('admin.orders.pay', order.id), {
        tendered: tendered.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            payDialogOpen.value = false;
            const payment = page.props.flash?.payment ?? null;

            lastPayment.value = { ...payment, order_id: order.id };
            notify.success(t('orders.paid_success'));
        },
        onError: () => notify.error(t('orders.tendered_too_low')),
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

    cart.value = [];
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

function startShift() {
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

async function applyDiscount() {
    const percent = discount.value.type === 'percent'
        ? discount.value.value
        : (subtotal.value > 0 ? discount.value.value / subtotal.value * 100 : 0);

    if (percent > 10 && ! props.canOverrideDiscount) {
        notify.error(t('orders.discount_limit_exceeded'));
        discount.value = { type: 'percent', value: 0 };

        return;
    }

    discountDialogOpen.value = false;
}

function printReceipt(orderId) {
    window.open(route('admin.orders.receipt', orderId), '_blank', 'width=380,height=640');
}

// keyboard shortcuts: / focus search, F2 new order (clear), F8 hold
function onKeydown(event) {
    if (event.key === '/' && document.activeElement?.tagName !== 'INPUT') {
        event.preventDefault();
        document.querySelector('[data-pos-search]')?.focus();
    } else if (event.key === 'F8') {
        event.preventDefault();
        holdOrder();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    if (! props.openShift) {
        shiftDialogOpen.value = true;
    }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

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

    router.post(route('payments.split', order.id), {
        lines: splitLines.value.map((line) => ({
            method: line.method,
            amount: line.amount,
            reference: line.reference || null,
        })),
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            payDialogOpen.value = false;
            lastPayment.value = { order_id: order.id, invoice_no: order.invoice_no, change_due: 0 };
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

    router.post(route('payments.manual', order.id), {
        method: walletMethod.value,
        amount: Number(latestOrder.value?.total ?? total.value),
        reference: walletTrxId.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            payDialogOpen.value = false;
            notify.success(t('payments.manual_recorded'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0] ?? t('common.error_occurred')),
    });
}

const changeDue = computed(() => Math.max(0, Number(tendered.value || 0) - (latestOrder.value ? Number(latestOrder.value.total) : total.value)));
</script>

<template>
    <div class="flex h-[calc(100vh-3.5rem)] flex-col gap-2 overflow-hidden p-2 lg:flex-row">
        <!-- Left: categories + items -->
        <section class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex items-center gap-2">
                <div class="flex h-11 flex-1 items-center gap-2 rounded-lg border border-border bg-surface px-3">
                    <AppIcon name="magnifying-glass" size="16" class="text-muted" />
                    <input
                        ref="searchRef"
                        v-model="search"
                        data-pos-search
                        type="search"
                        :placeholder="t('menu.search_items')"
                        class="w-full border-0 bg-transparent text-base text-foreground outline-none placeholder:text-muted/60"
                    >
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    class="h-9 rounded-lg border px-3 text-sm font-medium"
                    :class="activeCategory === null ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                    @click="activeCategory = null"
                >
                    {{ t('menu.all_items') }}
                </button>
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="h-9 rounded-lg border px-3 text-sm font-medium"
                    :class="activeCategory === category.id ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
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
                    class="flex min-h-20 flex-col items-start justify-between gap-1 rounded-lg border border-border bg-surface p-2.5 text-left transition-colors hover:border-accent"
                    @click="addItem(item)"
                >
                    <span class="line-clamp-2 text-sm font-medium text-foreground">{{ item.name }}</span>
                    <span class="flex w-full items-center justify-between">
                        <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(item.base_price) }}</span>
                        <AppIcon v-if="item.is_vegetarian" name="circle-check" size="12" class="text-success" />
                    </span>
                </button>
            </div>
        </section>

        <!-- Right: cart -->
        <section class="flex w-full shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-surface lg:w-[40%] lg:max-w-md">
            <header class="flex flex-wrap items-center gap-2 border-b border-border p-2">
                <RadioCards
                    v-model="orderType"
                    :options="[
                        { value: 'dine_in', label: t('orders.type.dine_in') },
                        { value: 'takeaway', label: t('orders.type.takeaway') },
                    ]"
                    class="flex-1"
                />
            </header>

            <div v-if="orderType === 'dine_in'" class="border-b border-border p-2">
                <select v-model="tableId" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm text-foreground">
                    <option :value="null" disabled>{{ t('orders.select_table') }}</option>
                    <option v-for="table in tables" :key="table.id" :value="table.id" :disabled="table.status === 'occupied'">
                        {{ table.name }} ({{ table.seats }}) {{ table.status === 'occupied' ? '- ' + t('orders.table_occupied') : '' }}
                    </option>
                </select>
            </div>

            <div class="border-b border-border p-2">
                <MobileInput v-model="customerMobile" :placeholder="t('orders.customer_mobile')" />
            </div>

            <!-- Held orders -->
            <div v-if="heldOrders.length" class="border-b border-border p-2">
                <p class="mb-1 text-xs font-medium text-muted">{{ t('orders.held_orders') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="held in heldOrders"
                        :key="held.id"
                        type="button"
                        class="flex h-8 items-center gap-1.5 rounded-md border border-border bg-background px-2 text-xs text-foreground hover:border-accent"
                        @click="resumeOrder(held)"
                    >
                        <AppIcon name="clock" size="12" class="text-warning" />
                        {{ held.cart.reduce((sum, line) => sum + line.quantity, 0) }} {{ t('ui_kit.items') }}
                        <span class="text-muted" @click.stop="discardHeld(held)">
                            <AppIcon name="xmark" size="10" />
                        </span>
                    </button>
                </div>
            </div>

            <!-- Cart lines -->
            <div class="thin-scrollbar flex-1 overflow-y-auto p-2">
                <p v-if="cart.length === 0" class="py-8 text-center text-sm text-muted">{{ t('orders.cart_empty') }}</p>

                <div v-for="(line, index) in cart" :key="`${line.item.id}-${line.variant?.id ?? 0}`" class="mb-2 rounded-lg border border-border bg-background/50 p-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-foreground">{{ line.item.name }}</p>
                            <p v-if="line.variant" class="text-xs text-muted">{{ line.variant.name }} - {{ formatMoney(line.variant.price) }}</p>
                        </div>
                        <button type="button" class="p-1 text-muted hover:text-danger" :aria-label="t('common.remove')" @click="setQuantity(line, 0)">
                            <AppIcon name="xmark" size="12" />
                        </button>
                    </div>

                    <div class="mt-1 flex items-center justify-between">
                        <div class="flex items-center gap-1">
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-md border border-border bg-surface text-lg" @click="setQuantity(line, line.quantity - 1)">&ndash;</button>
                            <span class="w-10 text-center text-base font-semibold tabular-nums text-foreground">{{ line.quantity }}</span>
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-md border border-border bg-surface" @click="setQuantity(line, line.quantity + 1)">
                                <AppIcon name="plus" size="16" />
                            </button>
                        </div>
                        <span class="text-base font-semibold tabular-nums text-foreground">{{ formatMoney(lineTotal(line)) }}</span>
                    </div>

                    <div v-if="line.item.add_ons?.length" class="mt-1.5 flex flex-wrap gap-1">
                        <button
                            v-for="addOn in line.item.add_ons"
                            :key="addOn.id"
                            type="button"
                            class="flex h-8 items-center gap-1 rounded-md border px-2 text-xs"
                            :class="hasAddOn(line, addOn) ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border text-muted'"
                            @click="toggleAddOn(line, addOn)"
                        >
                            <AppIcon v-if="hasAddOn(line, addOn)" name="check" size="10" />
                            {{ addOn.name }} +{{ formatMoney(addOn.price) }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Totals + actions -->
            <footer class="border-t border-border p-2">
                <div class="space-y-0.5 text-sm">
                    <div class="flex justify-between text-muted">
                        <span>{{ t('receipt.subtotal') }}</span>
                        <span class="tabular-nums">{{ formatMoney(subtotal) }}</span>
                    </div>
                    <div v-if="discountAmount > 0" class="flex justify-between text-muted">
                        <span>{{ t('receipt.discount') }}</span>
                        <span class="tabular-nums">-{{ formatMoney(discountAmount) }}</span>
                    </div>
                    <div v-if="serviceCharge > 0" class="flex justify-between text-muted">
                        <span>{{ t('receipt.service_charge') }}</span>
                        <span class="tabular-nums">{{ formatMoney(serviceCharge) }}</span>
                    </div>
                    <div v-if="vatAmount > 0" class="flex justify-between text-muted">
                        <span>{{ t('receipt.vat') }} ({{ Number(branch.vat_percent).toFixed(2) }}%)</span>
                        <span class="tabular-nums">{{ formatMoney(vatAmount) }}</span>
                    </div>
                    <div class="flex justify-between pt-1 text-lg font-bold text-foreground">
                        <span>{{ t('receipt.grand_total') }}</span>
                        <span class="tabular-nums">{{ formatMoney(total) }}</span>
                    </div>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-1.5">
                    <button
                        type="button"
                        class="flex h-11 items-center justify-center gap-1 rounded-lg border border-border bg-surface text-sm font-medium text-foreground hover:bg-background"
                        @click="discountDialogOpen = true"
                    >
                        <AppIcon name="tags" size="14" />
                        {{ t('orders.discount') }}
                    </button>
                    <button
                        type="button"
                        :disabled="cart.length === 0"
                        class="flex h-11 items-center justify-center gap-1 rounded-lg border border-border bg-surface text-sm font-medium text-foreground hover:bg-background disabled:opacity-50"
                        @click="holdOrder"
                    >
                        <AppIcon name="clock" size="14" />
                        {{ t('orders.hold') }}
                    </button>
                    <button
                        type="button"
                        :disabled="cart.length === 0 || (orderType === 'dine_in' && ! tableId)"
                        class="flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:opacity-50"
                        @click="submitOrder"
                    >
                        <AppIcon name="receipt" size="14" />
                        {{ t('orders.send') }}
                    </button>
                </div>
            </footer>
        </section>

        <!-- Pay dialog -->
        <Teleport to="body">
            <div v-if="payDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('orders.take_payment') }}</h2>
                    <p class="mt-0.5 text-sm text-muted">{{ latestOrder?.invoice_no }}</p>

                    <p class="mt-3 text-3xl font-bold tabular-nums text-foreground">{{ formatMoney(latestOrder?.total ?? total) }}</p>

                    <!-- Method tabs -->
                    <div class="mt-3 grid grid-cols-3 gap-1.5">
                        <button
                            v-for="mode in ['cash', 'split', 'wallet']"
                            :key="mode"
                            type="button"
                            class="flex h-9 items-center justify-center gap-1 rounded-md border text-sm font-medium"
                            :class="payMode === mode ? 'border-accent bg-accent/15 text-accent-foreground' : 'border-border text-muted'"
                            @click="mode === 'split' ? startSplit() : (payMode = mode)"
                        >
                            <AppIcon :name="mode === 'cash' ? 'money-bill-wave' : mode === 'split' ? 'layer-group' : 'mobile-screen'" size="12" />
                            {{ mode === 'cash' ? t('payment_method.cash') : mode === 'split' ? t('payment_method.split') : t('payments.wallet') }}
                        </button>
                    </div>

                    <!-- Cash -->
                    <div v-if="payMode === 'cash'">
                        <div class="mt-3">
                            <FormField :label="t('orders.tendered')" required>
                                <MoneyInput v-model="tendered" />
                            </FormField>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="amount in [total, 100, 500, 1000, 2000]"
                                :key="amount"
                                type="button"
                                class="h-9 rounded-md border border-border px-3 text-sm font-medium text-foreground hover:bg-background"
                                @click="quickAmount(amount)"
                            >
                                {{ amount === total ? t('orders.exact') : formatMoney(amount, { decimals: 0 }) }}
                            </button>
                        </div>

                        <div class="mt-3 flex items-center justify-between rounded-lg bg-success/10 px-3 py-2">
                            <span class="text-sm font-medium text-foreground">{{ t('receipt.change') }}</span>
                            <span class="text-xl font-bold tabular-nums text-success">{{ formatMoney(changeDue) }}</span>
                        </div>
                    </div>

                    <!-- Split -->
                    <div v-else-if="payMode === 'split'">
                        <div v-for="(line, index) in splitLines" :key="index" class="mt-2 grid grid-cols-[90px_1fr_1fr] items-end gap-2">
                            <select v-model="line.method" class="h-9 rounded-md border border-border bg-background px-1.5 text-sm text-foreground">
                                <option value="cash">{{ t('payment_method.cash') }}</option>
                                <option value="bkash">{{ t('payment_method.bkash') }}</option>
                                <option value="nagad">{{ t('payment_method.nagad') }}</option>
                                <option value="card">{{ t('payment_method.card') }}</option>
                            </select>
                            <FormField :label="t('payments.amount')">
                                <MoneyInput v-model="line.amount" />
                            </FormField>
                            <FormField v-if="line.method !== 'cash' && line.method !== 'card'" :label="t('payments.trxid')">
                                <template #default="{ id }">
                                    <input :id="id" v-model="line.reference" type="text" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm text-foreground">
                                </template>
                            </FormField>
                        </div>

                        <div class="mt-2 flex items-center justify-between text-sm" :class="Math.abs(splitTotal() - Number(latestOrder?.total ?? total)) < 0.01 ? 'text-success' : 'text-danger'">
                            <span>{{ t('common.total') }}</span>
                            <span class="tabular-nums font-semibold">{{ formatMoney(splitTotal()) }}</span>
                        </div>
                    </div>

                    <!-- Manual wallet -->
                    <div v-else>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <FormField :label="t('payments.wallet_type')">
                                <select v-model="walletMethod" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm text-foreground">
                                    <option value="bkash">{{ t('payment_method.bkash') }}</option>
                                    <option value="nagad">{{ t('payment_method.nagad') }}</option>
                                </select>
                            </FormField>
                            <FormField :label="t('payments.trxid')" required>
                                <template #default="{ id }">
                                    <input :id="id" v-model="walletTrxId" type="text" placeholder="8N7D5XYZ" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm tabular-nums text-foreground">
                                </template>
                            </FormField>
                        </div>
                        <p class="mt-2 text-xs text-muted">{{ t('payments.manual_hint') }}</p>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm text-foreground hover:bg-background" @click="payDialogOpen = false">{{ t('common.cancel') }}</button>
                        <button
                            v-if="payMode === 'cash'"
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-success px-4 text-sm font-semibold text-white hover:opacity-90"
                            @click="payNow"
                        >
                            <AppIcon name="money-bill-wave" size="14" />
                            {{ t('orders.confirm_payment') }}
                        </button>
                        <button
                            v-else-if="payMode === 'split'"
                            type="button"
                            :disabled="Math.abs(splitTotal() - Number(latestOrder?.total ?? total)) > 0.01"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            @click="paySplit"
                        >
                            {{ t('orders.confirm_payment') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="! walletTrxId"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            @click="payWalletManual"
                        >
                            {{ t('payments.record_manual') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Payment success -->
        <Teleport to="body">
            <div v-if="lastPayment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-6 text-center">
                    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success/15 text-success">
                        <AppIcon name="circle-check" size="32" />
                    </span>
                    <h2 class="mt-3 text-lg font-semibold text-foreground">{{ t('orders.payment_complete') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ lastPayment.invoice_no }}</p>

                    <div class="mt-3 flex items-center justify-between rounded-lg bg-background px-3 py-2">
                        <span class="text-sm text-foreground">{{ t('receipt.change') }}</span>
                        <span class="text-2xl font-bold tabular-nums text-success">{{ formatMoney(lastPayment.change_due ?? 0) }}</span>
                    </div>

                    <div class="mt-4 flex justify-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border px-4 text-sm font-medium text-foreground hover:bg-background"
                            @click="printReceipt(lastPayment.order_id)"
                        >
                            <AppIcon name="print" size="14" />
                            {{ t('receipt.print') }}
                        </button>
                        <button
                            type="button"
                            class="h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:opacity-90"
                            @click="lastPayment = null"
                        >
                            {{ t('orders.new_order') }}
                        </button>
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

        <!-- Discount dialog -->
        <Teleport to="body">
            <div v-if="discountDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-4">
                    <h2 class="text-lg font-semibold text-foreground">{{ t('orders.discount') }}</h2>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <FormField :label="t('orders.discount_type')">
                            <select v-model="discount.type" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm text-foreground">
                                <option value="percent">%</option>
                                <option value="fixed">{{ t('orders.fixed_amount') }}</option>
                            </select>
                        </FormField>
                        <FormField :label="t('orders.discount_value')">
                            <MoneyInput v-model="discount.value" :prefix="discount.type === 'percent' ? '%' : ''" />
                        </FormField>
                    </div>

                    <p v-if="! canOverrideDiscount" class="mt-2 text-xs text-muted">{{ t('orders.discount_limit_hint') }}</p>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-md border border-border px-3 text-sm text-foreground hover:bg-background" @click="discountDialogOpen = false">{{ t('common.cancel') }}</button>
                        <button type="button" class="h-9 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground" @click="applyDiscount">{{ t('common.apply') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
