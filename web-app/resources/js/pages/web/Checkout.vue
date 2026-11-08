<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import RadioCards from '@/components/ui-kit/RadioCards.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { localized } from '@/lib/site';
import { cart, cartSubtotal, setQuantity } from '@/lib/cart';

const page = usePage();

const props = defineProps({
    branch: { type: Object, required: true },
    zones: { type: Array, required: true },
    delivery: { type: Object, default: () => ({ enabled: true }) },
    customer: { type: Object, default: null },
    addresses: { type: Array, default: () => [] },
    table: { type: Object, default: null },
    turnstileSiteKey: { type: String, default: null },
});

const orderType = ref(props.table ? 'dine_in' : (props.delivery.enabled ? 'delivery' : 'pickup'));
const zoneId = ref(null);
const address = ref('');
const name = ref(props.customer?.name ?? '');
const mobile = ref(props.customer?.mobile?.replace(/^88/, '') ?? '');
const guestOtp = ref('');
const otpSent = ref(false);
const paymentMethod = ref('cod');
const scheduledFor = ref('');
const couponCode = ref('');
const couponDiscount = ref(0);
const couponChecking = ref(false);
const couponFeedback = ref(null);

async function checkCoupon() {
    if (! couponCode.value.trim()) {
        return;
    }

    couponChecking.value = true;
    couponFeedback.value = null;

    try {
        const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';
        const response = await fetch(route('web.coupons.validate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code: couponCode.value, subtotal: cartSubtotal() }),
        });

        const payload = await response.json();

        if (payload.success) {
            couponDiscount.value = Number(payload.data.discount);
            couponFeedback.value = { ok: true, text: t('coupons.applied', { discount: payload.data.discount }) };
        } else {
            couponDiscount.value = 0;
            couponFeedback.value = { ok: false, text: payload.error?.message ?? t('coupons.not_found') };
        }
    } catch {
        couponFeedback.value = { ok: false, text: t('common.error_occurred') };
    } finally {
        couponChecking.value = false;
    }
}
const submitting = ref(false);
const otpSending = ref(false);
const errors = ref({});

const FIELD_ORDER = ['items', 'delivery_zone_id', 'delivery_address', 'customer_mobile', 'guest_otp'];

/**
 * Every distinct message to show in the summary at the top of the form.
 */
const errorMessages = computed(() => [...new Set(Object.values(errors.value).flat().filter(Boolean))]);

const selectedZone = computed(() => props.zones.find((zone) => zone.id === Number(zoneId.value)));

const discountedSubtotal = computed(() => Math.max(0, cartSubtotal() - couponDiscount.value));

const serviceCharge = computed(() => (discountedSubtotal.value * Number(props.branch.service_charge_percent)) / 100);

const vatBase = computed(() => discountedSubtotal.value + serviceCharge.value);

const vatAmount = computed(() => props.branch.vat_inclusive
    ? vatBase.value - (vatBase.value * 100 / (100 + Number(props.branch.vat_percent)))
    : vatBase.value * Number(props.branch.vat_percent) / 100);

const deliveryFee = computed(() => (orderType.value === 'delivery' ? Number(selectedZone.value?.fee ?? 0) : 0));
const deliveryMinOrder = computed(() => Number(selectedZone.value?.min_order ?? 0));
const deliveryEta = computed(() => selectedZone.value?.eta_minutes ?? null);
const fixedZones = computed(() => props.zones.filter((zone) => zone.fee_type !== 'flexible'));
const flexibleZones = computed(() => props.zones.filter((zone) => zone.fee_type === 'flexible'));

function zoneLabel(zone) {
    const name = localized(zone, 'name');
    const fee = formatMoney(zone.fee, { decimals: 0, symbol: true });
    const min = formatMoney(zone.min_order, { decimals: 0, symbol: true });

    return zone.fee_type === 'flexible'
        ? t('web.zone_option_flexible', { name, fee, min })
        : t('web.zone_option_fixed', { name, fee, min });
}

const total = computed(() => Math.round((props.branch.vat_inclusive ? vatBase.value : vatBase.value + vatAmount.value) + deliveryFee.value));

function chooseAddress(saved) {
    address.value = saved.address;

    if (saved.delivery_zone_id) {
        zoneId.value = saved.delivery_zone_id;
    }
}

/**
 * Checks everything the server checks so people see what is missing
 * before any request is sent.
 */
function validate() {
    const found = {};

    if (cart.lines.length === 0) {
        found.items = t('web.cart_empty');
    }

    if (orderType.value === 'delivery') {
        if (! zoneId.value) {
            found.delivery_zone_id = t('web.err_zone');
        } else if (cartSubtotal() < deliveryMinOrder.value) {
            found.delivery_zone_id = t('web.min_order_not_met', { min: deliveryMinOrder.value });
        }

        if (! address.value.trim()) {
            found.delivery_address = t('web.err_address');
        }
    }

    if (! props.customer) {
        if (! /^01[3-9]\d{8}$/.test(mobile.value)) {
            found.customer_mobile = t('web.err_mobile');
        }

        if (! /^\d{6}$/.test(guestOtp.value)) {
            found.guest_otp = otpSent.value ? t('web.err_otp_length') : t('web.otp_first');
        }
    }

    return found;
}

async function focusFirstError(keys) {
    await nextTick();

    const first = FIELD_ORDER.find((key) => keys.includes(key)) ?? keys[0];
    const field = document.getElementById(`field-${first}`);

    if (field) {
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus({ preventScroll: true });
    } else {
        document.getElementById('checkout-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function sendOtp() {
    if (! /^01[3-9]\d{8}$/.test(mobile.value)) {
        errors.value = { ...errors.value, customer_mobile: t('web.err_mobile') };
        focusFirstError(['customer_mobile']);

        return;
    }

    otpSending.value = true;

    router.post(route('web.customer.otp.send'), { mobile: mobile.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            otpSent.value = true;
            const { customer_mobile: _removed, ...rest } = errors.value;
            errors.value = rest;
        },
        onError: (errs) => {
            const message = errs.mobile ?? Object.values(errs)[0] ?? t('common.error_occurred');
            errors.value = { ...errors.value, customer_mobile: message };
        },
        onFinish: () => (otpSending.value = false),
    });
}

function submit() {
    if (submitting.value) {
        return;
    }

    const found = validate();

    if (Object.keys(found).length > 0) {
        errors.value = found;
        focusFirstError(Object.keys(found));

        return;
    }

    submitting.value = true;
    errors.value = {};

    router.post(route('web.checkout.place'), {
        order_type: orderType.value,
        delivery_zone_id: orderType.value === 'delivery' ? zoneId.value : null,
        delivery_address: orderType.value === 'delivery' ? address.value : null,
        customer_name: name.value || null,
        customer_mobile: props.customer ? undefined : mobile.value,
        guest_otp: props.customer ? undefined : guestOtp.value,
        scheduled_for: scheduledFor.value || null,
        table_id: orderType.value === 'dine_in' ? props.table?.id : null,
        payment_method: paymentMethod.value,
        coupon_code: couponCode.value || null,
        turnstile_token: window.turnstileToken ?? null,
        items: cart.lines.map((line) => ({
            menu_item_id: line.itemId,
            variant_id: line.variantId,
            quantity: line.quantity,
            add_ons: line.addOns.map((addOn) => ({ id: addOn.id, qty: 1 })),
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.lines.splice(0);
        },
        onError: (errs) => {
            errors.value = errs;
            focusFirstError(Object.keys(errs));
        },
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Head :title="t('web.checkout_title')" />

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-5">
            <div v-if="errorMessages.length" id="checkout-errors" role="alert" class="rounded-xl border border-danger/40 bg-danger/10 p-4">
                <p class="flex items-center gap-2 text-sm font-semibold text-danger">
                    <AppIcon name="triangle-exclamation" size="14" />
                    {{ t('web.checkout_fix') }}
                </p>
                <ul class="mt-2 list-disc space-y-0.5 pl-9 text-sm text-foreground">
                    <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                </ul>
            </div>

            <!-- Order type -->
            <section class="rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.how_to_get_order') }}</h2>

                <div class="mt-2">
                    <RadioCards
                        v-model="orderType"
                        :options="[
                            ...(delivery.enabled ? [{ value: 'delivery', label: t('orders.type.delivery'), hint: deliveryEta ? deliveryEta + ' min' : null }] : []),
                            { value: 'pickup', label: t('orders.type.takeaway') },
                            ...(table ? [{ value: 'dine_in', label: t('web.at_table', { table: table.name }) }] : []),
                        ]"
                    />
                </div>
            </section>

            <!-- Delivery details -->
            <section v-if="orderType === 'delivery'" class="space-y-3 rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.delivery_details') }}</h2>

                <FormField :label="t('web.zone')" required for="field-delivery_zone_id" :error="errors.delivery_zone_id">
                    <template #default>
                        <select id="field-delivery_zone_id" v-model="zoneId" :aria-invalid="!! errors.delivery_zone_id" class="h-9 w-full rounded-md border bg-surface px-2 text-sm" :class="errors.delivery_zone_id ? 'border-danger' : 'border-border'">
                            <option :value="null" disabled>{{ t('common.select') }}</option>
                            <optgroup v-if="fixedZones.length" :label="t('web.zones_fixed')">
                                <option v-for="zone in fixedZones" :key="zone.id" :value="zone.id">{{ zoneLabel(zone) }}</option>
                            </optgroup>
                            <optgroup v-if="flexibleZones.length" :label="t('web.zones_flexible')">
                                <option v-for="zone in flexibleZones" :key="zone.id" :value="zone.id">{{ zoneLabel(zone) }}</option>
                            </optgroup>
                        </select>
                    </template>
                </FormField>

                <div v-if="addresses.length" class="flex flex-wrap gap-1.5">
                    <button
                        v-for="saved in addresses"
                        :key="saved.id"
                        type="button"
                        class="rounded-lg border border-border px-2.5 py-1.5 text-xs text-foreground"
                        @click="chooseAddress(saved)"
                    >
                        {{ saved.label ?? saved.address.slice(0, 25) }}
                    </button>
                </div>

                <p v-if="selectedZone?.fee_type === 'flexible'" class="rounded-md border border-border bg-background px-2.5 py-1.5 text-xs text-muted">{{ t('web.zone_flexible_note') }}</p>

                <FormField :label="t('web.exact_location')" :helper="t('web.address_helper')" required for="field-delivery_address" :error="errors.delivery_address">
                    <template #default>
                        <textarea id="field-delivery_address" v-model="address" :aria-invalid="!! errors.delivery_address" rows="2" class="w-full resize-y rounded-md border bg-surface px-2 py-1.5 text-sm" :class="errors.delivery_address ? 'border-danger' : 'border-border'" />
                    </template>
                </FormField>

                <p v-if="deliveryEta" class="flex items-center gap-1.5 text-xs text-muted">
                    <AppIcon name="motorcycle" size="12" />
                    {{ t('web.eta', { minutes: deliveryEta }) }}
                </p>
            </section>

            <!-- Guest identity -->
            <section v-if="! customer" class="space-y-3 rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.your_details') }}</h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('staff.name')">
                        <template #default="{ id }">
                            <input :id="id" v-model="name" type="text" class="h-9 w-full rounded-md border border-border bg-surface px-2 text-sm">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')" required for="field-customer_mobile" :error="errors.customer_mobile">
                        <div class="flex items-start gap-1.5">
                            <MobileInput v-model="mobile" input-id="field-customer_mobile" :invalid="!! errors.customer_mobile" />
                            <button type="button" :disabled="otpSending" class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground disabled:opacity-60" @click="sendOtp">
                                <AppIcon v-if="otpSending" name="spinner" size="11" spin />
                                {{ otpSending ? t('web.sending') : (otpSent ? t('web.resend_otp') : t('web.send_otp')) }}
                            </button>
                        </div>
                    </FormField>
                </div>

                <FormField :label="t('web.otp_code')" required for="field-guest_otp" :error="errors.guest_otp" :helper="otpSent ? t('web.otp_helper') : t('web.otp_first')">
                    <template #default>
                        <input id="field-guest_otp" v-model="guestOtp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" :aria-invalid="!! errors.guest_otp" class="h-9 w-full rounded-md border bg-surface px-2 text-center text-lg tracking-[0.4em] tabular-nums" :class="errors.guest_otp ? 'border-danger' : 'border-border'">
                    </template>
                </FormField>

                <div v-if="turnstileSiteKey" class="text-xs text-muted">{{ t('web.turnstile_note') }}</div>
            </section>

            <!-- Payment -->
            <section class="rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.payment_method') }}</h2>

                <div class="mt-2">
                    <RadioCards
                        v-model="paymentMethod"
                        :options="[
                            { value: 'cod', label: t('web.cod') },
                            { value: 'bkash', label: t('payment_method.bkash') },
                            { value: 'sslcommerz', label: t('payment_method.card') },
                        ]"
                    />
                </div>
            </section>
        </div>

        <!-- Summary -->
        <aside class="h-fit space-y-2 rounded-xl border border-border bg-surface p-4 lg:sticky lg:top-20">
            <h2 class="text-sm font-semibold">{{ t('web.order_summary') }}</h2>

            <ul v-if="cart.lines.length" class="space-y-2">
                <li v-for="(line, index) in cart.lines" :key="index" class="flex items-start justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ line.name }}</p>
                        <p v-if="line.variantName" class="text-xs text-muted">{{ line.variantName }}</p>
                        <p v-if="line.addOns.length" class="truncate text-xs text-muted">+ {{ line.addOns.map((a) => a.name).join(', ') }}</p>

                        <div class="mt-1 flex items-center gap-1.5">
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded border border-border" @click="setQuantity(index, line.quantity - 1)">&ndash;</button>
                            <span class="w-6 text-center font-semibold tabular-nums">{{ line.quantity }}</span>
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded border border-border" @click="setQuantity(index, line.quantity + 1)">+</button>
                        </div>
                    </div>
                    <span class="font-medium tabular-nums">{{ formatMoney((line.unitPrice + line.addOns.reduce((s, a) => s + a.price, 0)) * line.quantity) }}</span>
                </li>
            </ul>

            <p v-else class="py-6 text-center text-sm text-muted">{{ t('web.cart_empty') }}</p>

            <div class="space-y-1 border-t border-border pt-2 text-sm">
                <div class="flex justify-between text-muted">
                    <span>{{ t('receipt.subtotal') }}</span>
                    <span class="tabular-nums">{{ formatMoney(cartSubtotal()) }}</span>
                </div>
                <div v-if="couponDiscount > 0" class="flex justify-between text-muted">
                    <span>{{ t('receipt.discount') }} ({{ couponCode.toUpperCase() }})</span>
                    <span class="tabular-nums">-{{ formatMoney(couponDiscount) }}</span>
                </div>
                <div v-if="serviceCharge > 0" class="flex justify-between text-muted">
                    <span>{{ t('receipt.service_charge') }}</span>
                    <span class="tabular-nums">{{ formatMoney(serviceCharge) }}</span>
                </div>
                <div v-if="vatAmount > 0" class="flex justify-between text-muted">
                    <span>{{ t('receipt.vat') }}</span>
                    <span class="tabular-nums">{{ formatMoney(vatAmount) }}</span>
                </div>
                <div v-if="deliveryFee > 0" class="flex justify-between text-muted">
                    <span>{{ t('web.delivery_fee') }}</span>
                    <span class="tabular-nums">{{ formatMoney(deliveryFee) }}</span>
                </div>
                <div class="flex justify-between pt-1 text-lg font-bold">
                    <span>{{ t('receipt.grand_total') }}</span>
                    <span class="tabular-nums">{{ formatMoney(total) }}</span>
                </div>
            </div>

            <button
                type="button"
                :disabled="submitting"
                class="mt-2 flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-accent text-base font-semibold text-accent-foreground hover:opacity-90 disabled:opacity-60"
                @click="submit"
            >
                <AppIcon v-if="submitting" name="spinner" spin />
                {{ submitting ? t('web.sending') : t('web.place_order') }}
            </button>

            <div class="mt-2 flex gap-1.5">
                <input v-model="couponCode" type="text" :placeholder="t('coupons.code')" class="h-8 flex-1 rounded-md border border-border bg-background px-2 text-sm font-mono uppercase">
                <button type="button" :disabled="couponChecking" class="h-8 rounded-md border border-border px-2.5 text-xs font-medium" @click="checkCoupon">
                    {{ t('common.apply') }}
                </button>
            </div>

            <p v-if="couponFeedback" role="status" class="text-xs" :class="couponFeedback.ok ? 'text-success' : 'text-danger'">{{ couponFeedback.text }}</p>

            <p v-if="orderType === 'delivery' && selectedZone && deliveryMinOrder > 0 && cartSubtotal() < deliveryMinOrder" class="text-center text-xs text-danger">
                {{ t('web.min_order_not_met', { min: deliveryMinOrder }) }}
            </p>
        </aside>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
