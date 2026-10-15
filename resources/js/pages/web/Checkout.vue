<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import RadioCards from '@/components/ui-kit/RadioCards.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { cart, cartSubtotal, setQuantity } from '@/lib/cart';
import { notify } from '@/lib/notify';

const page = usePage();

const props = defineProps({
    branch: { type: Object, required: true },
    zones: { type: Array, required: true },
    customer: { type: Object, default: null },
    addresses: { type: Array, default: () => [] },
    table: { type: Object, default: null },
    turnstileSiteKey: { type: String, default: null },
});

const orderType = ref(props.table ? 'dine_in' : 'delivery');
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

async function checkCoupon() {
    if (! couponCode.value.trim()) {
        return;
    }

    couponChecking.value = true;

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
            notify.success(t('coupons.applied', { discount: payload.data.discount }));
        } else {
            couponDiscount.value = 0;
            notify.error(payload.error?.message ?? t('coupons.not_found'));
        }
    } catch {
        notify.error(t('common.error_occurred'));
    } finally {
        couponChecking.value = false;
    }
}
const submitting = ref(false);
const errors = ref({});

const selectedZone = computed(() => props.zones.find((zone) => zone.id === Number(zoneId.value)));

const discountedSubtotal = computed(() => Math.max(0, cartSubtotal() - couponDiscount.value));

const serviceCharge = computed(() => (discountedSubtotal.value * Number(props.branch.service_charge_percent)) / 100);

const vatBase = computed(() => discountedSubtotal.value + serviceCharge.value);

const vatAmount = computed(() => props.branch.vat_inclusive
    ? vatBase.value - (vatBase.value * 100 / (100 + Number(props.branch.vat_percent)))
    : vatBase.value * Number(props.branch.vat_percent) / 100);

const deliveryFee = computed(() => (orderType.value === 'delivery' && selectedZone.value ? Number(selectedZone.value.fee) : 0));

const total = computed(() => Math.round((props.branch.vat_inclusive ? vatBase.value : vatBase.value + vatAmount.value) + deliveryFee.value));

function chooseAddress(saved) {
    address.value = saved.address;

    if (saved.delivery_zone_id) {
        zoneId.value = saved.delivery_zone_id;
    }
}

function sendOtp() {
    router.post(route('web.customer.otp.send'), { mobile: mobile.value }, {
        preserveState: true,
        onSuccess: () => {
            otpSent.value = true;
            notify.success(t('web.otp_sent'));
        },
        onError: (errs) => notify.error(Object.values(errs)[0]),
    });
}

function submit() {
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
        onSuccess: () => {
            cart.lines.splice(0);
        },
        onError: (errs) => {
            errors.value = errs;
            submitting.value = false;
            notify.error(Object.values(errs)[0] ?? t('common.error_occurred'));
        },
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Head :title="t('web.checkout_title')" />

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-5">
            <!-- Order type -->
            <section class="rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.how_to_get_order') }}</h2>

                <div class="mt-2">
                    <RadioCards
                        v-model="orderType"
                        :options="[
                            { value: 'delivery', label: t('orders.type.delivery'), hint: selectedZone ? selectedZone.eta_minutes + ' min' : null },
                            { value: 'pickup', label: t('orders.type.takeaway') },
                            ...(table ? [{ value: 'dine_in', label: t('web.at_table', { table: table.name }) }] : []),
                        ]"
                    />
                </div>
            </section>

            <!-- Delivery details -->
            <section v-if="orderType === 'delivery'" class="space-y-3 rounded-xl border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold">{{ t('web.delivery_details') }}</h2>

                <FormField :label="t('web.zone')" required :error="errors.delivery_zone_id">
                    <template #default="{ id }">
                        <select :id="id" v-model="zoneId" class="h-9 w-full rounded-md border bg-surface px-2 text-sm" :class="errors.delivery_zone_id ? 'border-danger' : 'border-border'">
                            <option :value="null" disabled>{{ t('common.select') }}</option>
                            <option v-for="zone in zones" :key="zone.id" :value="zone.id">
                                {{ zone.name }} - {{ formatMoney(zone.fee) }} ({{ t('web.min_order') }}: {{ formatMoney(zone.min_order) }})
                            </option>
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

                <FormField :label="t('web.address')" required :error="errors.delivery_address">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="address" rows="2" class="w-full resize-y rounded-md border bg-surface px-2 py-1.5 text-sm" :class="errors.delivery_address ? 'border-danger' : 'border-border'" />
                    </template>
                </FormField>

                <p v-if="selectedZone" class="flex items-center gap-1.5 text-xs text-muted">
                    <AppIcon name="motorcycle" size="12" />
                    {{ t('web.eta', { minutes: selectedZone.eta_minutes }) }}
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

                    <FormField :label="t('staff.mobile')" required :error="errors.customer_mobile">
                        <div class="flex gap-1.5">
                            <MobileInput v-model="mobile" />
                            <button type="button" class="h-9 shrink-0 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground" @click="sendOtp">
                                {{ otpSent ? t('web.resend_otp') : t('web.send_otp') }}
                            </button>
                        </div>
                    </FormField>
                </div>

                <FormField v-if="otpSent" :label="t('web.otp_code')" required :error="errors.guest_otp" :helper="t('web.otp_helper')">
                    <template #default="{ id }">
                        <input :id="id" v-model="guestOtp" type="text" inputmode="numeric" maxlength="6" class="h-9 w-full rounded-md border bg-surface px-2 text-center text-lg tracking-[0.4em] tabular-nums" :class="errors.guest_otp ? 'border-danger' : 'border-border'">
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
                :disabled="cart.lines.length === 0 || submitting || (orderType === 'delivery' && ! zoneId)"
                class="mt-2 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-accent text-base font-semibold text-accent-foreground disabled:opacity-50"
                @click="submit"
            >
                <AppIcon v-if="submitting" name="spinner" spin />
                {{ t('web.place_order') }}
            </button>

            <div class="mt-2 flex gap-1.5">
                <input v-model="couponCode" type="text" :placeholder="t('coupons.code')" class="h-8 flex-1 rounded-md border border-border bg-background px-2 text-sm font-mono uppercase">
                <button type="button" :disabled="couponChecking" class="h-8 rounded-md border border-border px-2.5 text-xs font-medium" @click="checkCoupon">
                    {{ t('common.apply') }}
                </button>
            </div>

            <p v-if="selectedZone && cartSubtotal() < Number(selectedZone.min_order)" class="text-center text-xs text-danger">
                {{ t('web.min_order_not_met', { min: selectedZone.min_order }) }}
            </p>
        </aside>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
