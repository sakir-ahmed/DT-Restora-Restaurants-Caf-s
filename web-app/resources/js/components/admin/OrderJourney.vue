<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { t } from '@/lib/i18n';

// The steps an order goes through: the kitchen steps, and payment apart from them (it can come
// first, later, or be left as a due). Two grouped buttons move the kitchen step back or forward,
// named after the step they go to.
const props = defineProps({
    order: { type: Object, required: true },
    canChange: { type: Boolean, default: false },
});

const emit = defineEmits(['status']);

const KITCHEN = ['pending', 'preparing', 'ready', 'served'];

const isVoid = computed(() => props.order.status === 'void' || props.order.deleted === true);
const kitchenIndex = computed(() => Math.max(0, KITCHEN.indexOf(props.order.order_status)));
const allowed = computed(() => props.order.next_statuses ?? []);

// Where the food goes: a table number, or takeaway or delivery.
const placeText = computed(() => {
    if (props.order.order_type === 'dine_in') {
        return props.order.table_name ? `${t('receipt.table')} ${props.order.table_name}` : t('orders.type.dine_in');
    }

    return props.order.order_type ? t('orders.type.' + props.order.order_type) : '';
});

const steps = computed(() => {
    const list = KITCHEN.map((key, index) => {
        let state = 'upcoming';

        if (isVoid.value) {
            state = index === 0 ? 'done' : 'skipped';
        } else if (index < kitchenIndex.value) {
            state = 'done';
        } else if (index === kitchenIndex.value) {
            state = key === 'served' ? 'done' : 'current';
        }

        return {
            key,
            label: t('status.' + key),
            state,
            icon: { pending: 'clock', preparing: 'hourglass-half', ready: 'bell', served: 'utensils' }[key],
            caption: key === 'served' ? placeText.value : '',
        };
    });

    let payment = { state: 'upcoming', caption: t('status.unpaid') };

    if (isVoid.value) {
        payment = { state: 'skipped', caption: '' };
    } else if (props.order.payment_status === 'paid') {
        payment = { state: 'done', caption: t('status.paid') };
    } else if (props.order.payment_status === 'due') {
        payment = { state: 'current', caption: t('status.due'), warn: true };
    } else if (props.order.payment_status === 'partial') {
        payment = { state: 'current', caption: t('status.partial'), warn: true };
    }

    list.push({ key: 'payment', label: t('orders.payment_status'), icon: 'money-bill-wave', ...payment });

    if (isVoid.value) {
        list.push({ key: 'void', label: t('status.void'), state: 'void', icon: 'trash', caption: '' });
    }

    return list;
});

function reachable(key) {
    return props.canChange && ! isVoid.value && allowed.value.includes(key);
}

const previous = computed(() => {
    const key = KITCHEN[kitchenIndex.value - 1];

    return key && reachable(key) ? { key, label: t('status.' + key) } : null;
});

const next = computed(() => {
    const key = KITCHEN[kitchenIndex.value + 1];

    return key && reachable(key) ? { key, label: t('status.' + key) } : null;
});

function circleClasses(step) {
    return {
        done: 'border-success bg-success text-white',
        current: step.warn ? 'border-warning bg-warning/20 text-warning ring-2 ring-warning/25' : 'border-accent bg-accent/15 text-primary ring-2 ring-accent/25',
        upcoming: 'border-border bg-surface text-muted',
        skipped: 'border-border bg-background text-muted/60',
        void: 'border-danger bg-danger text-white',
    }[step.state];
}

function tooltip(step) {
    return [step.label, step.caption].filter(Boolean).join(': ');
}

const buttonClasses = 'inline-flex h-6 items-center gap-1 bg-surface px-2 text-xs font-medium text-foreground hover:bg-background';
</script>

<template>
    <div class="rounded-md border border-border px-2 py-2">
        <ol class="flex items-start" :aria-label="t('orders.journey_title')">
            <li v-for="(step, index) in steps" :key="step.key" class="relative flex min-w-0 flex-1 flex-col items-center" :title="tooltip(step)">
                <span
                    v-if="index > 0"
                    class="absolute right-1/2 top-[9px] h-0.5 w-full"
                    :class="steps[index - 1].state === 'done' ? 'bg-success' : 'bg-border'"
                    aria-hidden="true"
                />
                <span class="relative z-10 flex h-5 w-5 items-center justify-center rounded-full border-2" :class="circleClasses(step)">
                    <AppIcon :name="step.state === 'done' ? 'check' : step.icon" size="9" />
                </span>
                <span
                    class="mt-0.5 w-full truncate px-0.5 text-center text-[11px] leading-tight"
                    :class="step.state === 'upcoming' || step.state === 'skipped' ? 'text-muted' : 'font-semibold text-foreground'"
                >{{ step.label }}</span>
                <span v-if="step.caption" class="w-full truncate px-0.5 text-center text-[10px] leading-tight" :class="step.warn ? 'text-warning' : 'text-muted'">{{ step.caption }}</span>
            </li>
        </ol>

        <div v-if="previous || next" class="mt-1.5 flex justify-center border-t border-border pt-1.5">
            <div class="inline-flex divide-x divide-border overflow-hidden rounded-md border border-border">
                <button v-if="previous" type="button" :class="buttonClasses" :title="t('orders.move_back', { status: previous.label })" @click="emit('status', order, previous.key)">
                    <AppIcon name="arrow-left" size="10" />
                    {{ previous.label }}
                </button>
                <button v-if="next" type="button" :class="buttonClasses" :title="t('orders.mark_as', { status: next.label })" @click="emit('status', order, next.key)">
                    {{ next.label }}
                    <AppIcon name="arrow-right" size="10" />
                </button>
            </div>
        </div>
    </div>
</template>
