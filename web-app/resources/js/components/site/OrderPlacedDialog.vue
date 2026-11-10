<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';
import { formatMoney } from '@/lib/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    order: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const primary = ref(null);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(() => props.open, async (open) => {
    if (open) {
        await nextTick();
        primary.value?.$el?.focus();
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="open && order" class="site fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 text-foreground" @click.self="emit('close')">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-6 text-center shadow-xl" role="dialog" aria-modal="true" aria-labelledby="order-placed-title">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/15 text-success">
                        <AppIcon name="circle-check" size="28" />
                    </span>

                    <h2 id="order-placed-title" class="mt-4 text-xl font-bold">{{ t('web.order_success_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ t('web.order_success_text') }}</p>

                    <dl class="mt-5 divide-y divide-border rounded-lg border border-border text-left text-sm">
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <dt class="text-muted">{{ t('web.order_success_invoice') }}</dt>
                            <dd class="font-semibold tabular-nums">{{ order.invoice_no }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <dt class="text-muted">{{ t('web.order_success_total') }}</dt>
                            <dd class="font-semibold tabular-nums">{{ formatMoney(order.total, { decimals: 0 }) }} {{ t('site.currency') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <dt class="text-muted">{{ t('web.order_success_payment') }}</dt>
                            <dd class="font-semibold">{{ order.payment_method === 'cod' ? t('web.order_success_pay_cod') : t(`payment_method.${order.payment_method === 'sslcommerz' ? 'card' : order.payment_method}`) }}</dd>
                        </div>
                        <div v-if="order.eta_minutes" class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <dt class="text-muted">{{ t('web.order_success_eta') }}</dt>
                            <dd class="font-semibold">{{ t('site.zone_minutes', { minutes: formatMoney(order.eta_minutes, { decimals: 0 }) }) }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs text-muted">{{ t('web.order_success_sms') }}</p>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                        <button ref="primary" type="button" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-base font-semibold text-accent-foreground hover:opacity-90" @click="emit('close')">
                            <AppIcon name="truck-fast" size="14" />
                            {{ t('web.order_success_track') }}
                        </button>
                        <Link href="/menu" class="inline-flex h-11 flex-1 items-center justify-center rounded-lg border border-border px-4 text-base font-medium hover:bg-background">
                            {{ t('web.order_success_more') }}
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
