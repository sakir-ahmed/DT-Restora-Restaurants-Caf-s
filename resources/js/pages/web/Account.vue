<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import DateText from '@/components/ui-kit/DateText.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';
import { addToCart } from '@/lib/cart';

defineProps({
    customer: { type: Object, required: true },
    orders: { type: Array, required: true },
    addresses: { type: Array, required: true },
});

const confirm = useConfirm();
const tab = ref('orders');

const addressForm = ref({ label: '', address: '', landmark: '', delivery_zone_id: null });
const zones = ref([]);

function saveAddress() {
    router.post(route('web.account.addresses.store'), addressForm.value, {
        preserveState: true,
        onSuccess: () => {
            addressForm.value = { label: '', address: '', landmark: '', delivery_zone_id: null };
        },
    });
}

async function deleteAccount() {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('web.delete_account_title'),
        message: t('web.delete_account_message'),
        confirmText: t('web.delete_account'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('web.account.destroy'));
}

async function removeAddress(address) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('web.address_deleted_title'),
        message: t('common.confirm'),
        confirmText: t('common.delete'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('web.account.addresses.destroy', address.id));
}

function reorder(order) {
    router.visit(`/menu`, {
        onSuccess: () => {
            order.items.forEach((item) => {
                addToCart({ id: item.menu_item_id, name: item.name, base_price: item.line_total / item.quantity });
            });
        },
    });
}

function logout() {
    router.post(route('web.customer.logout'));
}
</script>

<template>
    <Head :title="t('web.nav_account')" />

    <div>
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-bold">{{ customer.name }}</h1>
                <p class="text-sm text-muted">{{ customer.mobile }}</p>
            </div>

            <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border px-3 text-sm text-muted hover:text-foreground" @click="logout">
                <AppIcon name="arrow-right-from-bracket" size="12" />
                {{ t('nav.logout') }}
            </button>
        </header>

        <div class="mb-4 mt-5 flex gap-1 border-b border-border">
            <button
                v-for="key in ['orders', 'addresses']"
                :key="key"
                type="button"
                class="-mb-px border-b-2 px-3 py-1.5 text-sm font-medium"
                :class="tab === key ? 'border-accent text-foreground' : 'border-transparent text-muted'"
                @click="tab = key"
            >
                {{ key === 'orders' ? t('web.my_orders') : t('web.my_addresses') }}
            </button>
        </div>

        <!-- Orders -->
        <div v-if="tab === 'orders'" class="space-y-2">
            <p v-if="orders.length === 0" class="py-8 text-center text-sm text-muted">{{ t('web.no_orders') }}</p>

            <article v-for="order in orders" :key="order.id" class="rounded-xl border border-border bg-surface p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold">{{ order.invoice_no }}</p>
                        <p class="text-xs text-muted"><DateText :value="order.created_at" /></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <StatusBadge :status="order.status === 'paid' ? 'paid' : order.status" />
                        <span class="font-semibold tabular-nums">{{ formatMoney(order.total) }}</span>
                    </div>
                </div>

                <p class="mt-1.5 truncate text-xs text-muted">{{ order.items.map((item) => item.quantity + 'x ' + item.name).join(', ') }}</p>

                <div class="mt-2 flex gap-2">
                    <a :href="`/track/${order.tracking_token}`" class="inline-flex h-8 items-center gap-1 rounded-lg border border-border px-2.5 text-xs font-medium text-foreground">
                        <AppIcon name="magnifying-glass" size="10" />
                        {{ t('web.track_order') }}
                    </a>
                    <button type="button" class="inline-flex h-8 items-center gap-1 rounded-lg border border-border px-2.5 text-xs font-medium text-foreground" @click="reorder(order)">
                        <AppIcon name="rotate" size="10" />
                        {{ t('web.reorder') }}
                    </button>
                </div>
            </article>
        </div>

        <!-- Addresses -->
        <div v-else class="space-y-4">
            <div v-if="addresses.length" class="space-y-2">
                <article v-for="address in addresses" :key="address.id" class="flex items-start justify-between gap-2 rounded-xl border border-border bg-surface p-3">
                    <div>
                        <p v-if="address.label" class="text-sm font-semibold">{{ address.label }}</p>
                        <p class="text-sm text-muted">{{ address.address }}</p>
                        <p v-if="address.zone" class="mt-0.5 text-xs text-muted">{{ address.zone.name }} ({{ formatMoney(address.zone.fee) }})</p>
                    </div>
                    <button type="button" class="p-1 text-muted hover:text-danger" :aria-label="t('common.delete')" @click="removeAddress(address)">
                        <AppIcon name="trash" size="12" />
                    </button>
                </article>
            </div>

            <form class="max-w-md space-y-3 rounded-xl border border-border bg-surface p-4" @submit.prevent="saveAddress">
                <h2 class="text-sm font-semibold">{{ t('web.add_address') }}</h2>

                <FormField :label="t('web.address_label')">
                    <template #default="{ id }">
                        <input :id="id" v-model="addressForm.label" type="text" :placeholder="t('web.address_label_hint')" class="h-9 w-full rounded-md border border-border bg-surface px-2 text-sm">
                    </template>
                </FormField>

                <FormField :label="t('web.address')" required>
                    <template #default="{ id }">
                        <textarea :id="id" v-model="addressForm.address" rows="2" class="w-full resize-y rounded-md border border-border bg-surface px-2 py-1.5 text-sm" />
                    </template>
                </FormField>

                <button type="submit" class="h-9 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground">
                    {{ t('common.save') }}
                </button>
            </form>
        </div>

        <button type="button" class="mt-6 text-sm text-danger" @click="deleteAccount">
            {{ t('web.delete_account') }}
        </button>
    </div>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default { layout: PublicLayout };
</script>
