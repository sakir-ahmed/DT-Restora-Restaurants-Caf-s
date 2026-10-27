<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';
import { printUrl } from '@/lib/print';
import { localized } from '@/lib/site';

const props = defineProps({
    reservations: { type: Array, required: true },
    tables: { type: Array, required: true },
    menu: { type: Array, default: () => [] },
    menuCategories: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    range: { type: Object, required: true },
    branchName: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});

const confirm = useConfirm();
const open = ref(false);
const editing = ref(null);
const advanceFor = ref(null);
const menuSearch = ref('');
const menuCategory = ref(null);
const wantsAdvance = ref(false);

const DURATIONS = [60, 90, 120, 180, 240];
const inputClass = 'h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40 border-border focus:border-accent';

// The page works on this browser's day, and sends the server real moments in time.
function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function dateInput(date) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function timeInput(date) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const day = ref(startOfDay(new Date()));

function load(target) {
    day.value = startOfDay(target);

    const from = day.value;
    const to = new Date(from.getFullYear(), from.getMonth(), from.getDate() + 1);

    router.get(route('admin.reservations.index'), { from: from.toISOString(), to: to.toISOString() }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['reservations', 'range'],
    });
}

function shiftDay(step) {
    load(new Date(day.value.getFullYear(), day.value.getMonth(), day.value.getDate() + step));
}

onMounted(() => {
    // The first load used the server's idea of today; show this browser's day instead.
    if (Math.abs(new Date(props.range.from).getTime() - day.value.getTime()) > 60000) {
        load(day.value);
    }
});

const dayLabel = computed(() => day.value.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
const isToday = computed(() => dateInput(day.value) === dateInput(new Date()));

function clock(iso) {
    return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

function holdingNow(reservation) {
    const now = Date.now();

    return reservation.status === 'booked' && new Date(reservation.starts_at).getTime() <= now && new Date(reservation.ends_at).getTime() > now;
}

const summary = computed(() => ({
    booked: props.reservations.filter((entry) => entry.status === 'booked').length,
    seated: props.reservations.filter((entry) => entry.status === 'seated').length,
    guests: props.reservations.filter((entry) => ['booked', 'seated'].includes(entry.status)).reduce((sum, entry) => sum + entry.party_size, 0),
    held: props.reservations.reduce((sum, entry) => sum + Number(entry.deposit.balance), 0),
}));

const STATUS_CLASSES = {
    booked: 'bg-accent/15 text-primary',
    seated: 'bg-success/10 text-success',
    completed: 'bg-muted/10 text-muted',
    cancelled: 'bg-danger/10 text-danger',
    no_show: 'bg-warning/15 text-warning',
};

const DEPOSIT_CLASSES = {
    held: 'bg-accent/15 text-primary',
    applied: 'bg-success/10 text-success',
    refunded: 'bg-muted/10 text-muted',
    forfeited: 'bg-warning/15 text-warning',
};

function holding(reservation) {
    return ['booked', 'seated'].includes(reservation.status);
}

// ---- the booking form ----

const blank = () => ({
    guest_name: '',
    guest_mobile: '',
    party_size: 2,
    table_ids: [],
    date: dateInput(day.value),
    time: '19:00',
    duration: 90,
    note: '',
    items: [],
    deposit_amount: '',
    deposit_method: props.paymentMethods[0]?.code ?? '',
    deposit_reference: '',
});

const form = useForm(blank());

const selectedTables = computed(() => form.table_ids.map((id) => props.tables.find((table) => table.id === id)).filter(Boolean));
const selectedSeats = computed(() => selectedTables.value.reduce((sum, table) => sum + Number(table.seats), 0));
const seatsShort = computed(() => form.table_ids.length > 0 && selectedSeats.value < Number(form.party_size));

function toggleTable(id) {
    form.table_ids = form.table_ids.includes(id) ? form.table_ids.filter((entry) => entry !== id) : [...form.table_ids, id];
}

function openCreate() {
    editing.value = null;
    menuSearch.value = '';
    menuCategory.value = null;
    wantsAdvance.value = false;
    form.defaults(blank()).reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(reservation) {
    const starts = new Date(reservation.starts_at);

    editing.value = reservation;
    menuSearch.value = '';
    menuCategory.value = null;
    wantsAdvance.value = false;
    form.defaults({
        ...blank(),
        guest_name: reservation.guest_name,
        guest_mobile: reservation.guest_mobile ?? '',
        party_size: reservation.party_size,
        table_ids: [...reservation.table_ids],
        date: dateInput(starts),
        time: timeInput(starts),
        duration: Math.round((new Date(reservation.ends_at) - starts) / 60000),
        note: reservation.note ?? '',
        items: (reservation.preorder ?? []).map((line) => ({
            menu_item_id: line.menu_item_id,
            variant_id: line.variant_id ?? null,
            quantity: line.quantity,
            add_ons: (line.add_ons ?? []).map((addOn) => ({ id: addOn.id, qty: addOn.qty })),
        })),
    }).reset();
    form.clearErrors();
    open.value = true;
}

function stepParty(delta) {
    form.party_size = Math.min(200, Math.max(1, Number(form.party_size || 1) + delta));
}

const endsAt = computed(() => {
    const starts = new Date(`${form.date}T${form.time}`);

    return Number.isNaN(starts.getTime()) ? null : new Date(starts.getTime() + Number(form.duration) * 60000);
});

const durations = computed(() => (DURATIONS.includes(Number(form.duration)) ? DURATIONS : [...DURATIONS, Number(form.duration)].sort((a, b) => a - b)));

function durationLabel(minutes) {
    return minutes % 60 === 0 && minutes >= 60 ? t('reservations.hours', { count: minutes / 60 }) : t('reservations.minutes', { count: minutes });
}

// ---- pre-order ----

function menuItem(id) {
    return props.menu.find((entry) => entry.id === id) ?? null;
}

const visibleDishes = computed(() => {
    const needle = menuSearch.value.trim().toLowerCase();

    return props.menu
        .filter((item) => (menuCategory.value === null || item.category_id === menuCategory.value)
            && (needle === '' || item.name.toLowerCase().includes(needle) || (item.name_bn ?? '').includes(menuSearch.value.trim())))
        .slice(0, 60);
});

// How many of a dish are on the pre-order, whatever size or extras.
function dishQty(id) {
    return form.items.filter((line) => line.menu_item_id === id).reduce((sum, line) => sum + Number(line.quantity), 0);
}

// Tapping a dish adds one in its first size; tapping again adds another of the same.
function addDish(item) {
    const variant = item.variants[0] ?? null;
    const same = form.items.find((line) => line.menu_item_id === item.id && line.variant_id === (variant?.id ?? null) && line.add_ons.length === 0);

    if (same) {
        same.quantity = Math.min(50, same.quantity + 1);

        return;
    }

    form.items = [...form.items, { menu_item_id: item.id, variant_id: variant?.id ?? null, quantity: 1, add_ons: [] }];
}

function lessDish(item) {
    const index = form.items.map((line) => line.menu_item_id).lastIndexOf(item.id);

    if (index === -1) {
        return;
    }

    if (form.items[index].quantity > 1) {
        form.items[index].quantity -= 1;
    } else {
        removeDish(index);
    }
}

function removeDish(index) {
    form.items = form.items.filter((_, position) => position !== index);
}

function addOnQty(line, id) {
    return line.add_ons.find((addOn) => addOn.id === id)?.qty ?? 0;
}

function setAddOn(line, id, qty) {
    const rest = line.add_ons.filter((addOn) => addOn.id !== id);

    line.add_ons = qty > 0 ? [...rest, { id, qty: Math.min(9, qty) }] : rest;
}

function lineTotal(line) {
    const item = menuItem(line.menu_item_id);

    if (! item) {
        return 0;
    }

    const variant = item.variants.find((entry) => entry.id === line.variant_id);
    const extras = line.add_ons.reduce((sum, addOn) => sum + Number(item.add_ons.find((entry) => entry.id === addOn.id)?.price ?? 0) * addOn.qty, 0);

    return (Number(variant?.price ?? item.base_price) + extras) * Number(line.quantity);
}

const preorderTotal = computed(() => form.items.reduce((sum, line) => sum + lineTotal(line), 0));

function methodNeedsReference(code) {
    return props.paymentMethods.find((method) => method.code === code)?.type === 'wallet';
}

function save() {
    form.transform((data) => {
        const starts = new Date(`${data.date}T${data.time}`);
        const valid = ! Number.isNaN(starts.getTime());
        const payload = {
            guest_name: data.guest_name,
            guest_mobile: data.guest_mobile || null,
            party_size: Number(data.party_size),
            table_ids: data.table_ids,
            starts_at: valid ? starts.toISOString() : null,
            ends_at: valid ? new Date(starts.getTime() + Number(data.duration) * 60000).toISOString() : null,
            note: data.note || null,
            items: data.items.map((line) => ({
                menu_item_id: line.menu_item_id,
                variant_id: line.variant_id,
                quantity: Number(line.quantity),
                add_ons: line.add_ons,
            })),
        };

        if (editing.value === null && Number(data.deposit_amount) > 0) {
            payload.deposit_amount = Number(data.deposit_amount);
            payload.deposit_method = data.deposit_method;
            payload.deposit_reference = data.deposit_reference || null;
        }

        return payload;
    });

    const options = {
        preserveScroll: true,
        onSuccess: (page) => {
            open.value = false;
            notify.success(page.props.flash?.success ?? t('reservations.updated'));
            load(new Date(`${form.date}T${form.time}`));
        },
    };

    if (editing.value === null) {
        form.post(route('admin.reservations.store'), options);

        return;
    }

    form.put(route('admin.reservations.update', editing.value.id), options);
}

// ---- actions on a booking ----

async function act(reservation, action) {
    if (action === 'cancel' || action === 'no-show') {
        const key = action === 'cancel' ? 'cancel' : 'no_show';
        const result = await confirm({
            variant: 'danger',
            level: 'simple',
            title: t(`reservations.${key}_title`),
            message: `${reservation.guest_name} - ${reservation.tables.map((table) => table.name).join(', ')}. ${t(`reservations.${key}_message`)}`,
            confirmText: t(`reservations.action_${key}`),
        });

        if (! result.confirmed) {
            return;
        }
    }

    router.post(route('admin.reservations.transition', { reservation: reservation.id, action }), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('reservations.updated')),
    });
}

function printConfirmation(reservation) {
    printUrl(route('admin.reservations.confirmation', reservation.id));
}

async function closeAdvance(reservation, outcome) {
    const result = await confirm({
        variant: outcome === 'refund' ? 'warning' : 'danger',
        level: 'simple',
        title: t(`reservations.deposit_${outcome}_title`),
        message: t(`reservations.deposit_${outcome}_message`, { amount: formatMoney(reservation.deposit.balance) }),
        confirmText: t(`reservations.deposit_${outcome}`),
    });

    if (! result.confirmed) {
        return;
    }

    router.post(route('admin.reservations.deposit.close', { reservation: reservation.id, outcome }), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => notify.success(page.props.flash?.success ?? t('reservations.updated')),
        onError: (errors) => notify.error(String(Object.values(errors)[0])),
    });
}

const advanceForm = useForm({ deposit_amount: '', deposit_method: '', deposit_reference: '' });

function openAdvance(reservation) {
    advanceFor.value = reservation;
    advanceForm.defaults({ deposit_amount: '', deposit_method: props.paymentMethods[0]?.code ?? '', deposit_reference: '' }).reset();
    advanceForm.clearErrors();
}

function saveAdvance() {
    advanceForm.post(route('admin.reservations.deposit', advanceFor.value.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            advanceFor.value = null;
            notify.success(page.props.flash?.success ?? t('reservations.deposit_collected'));
        },
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('reservations.title')" :description="t('reservations.subtitle', { branch: branchName })">
            <template v-if="canManage" #actions>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="openCreate">
                    <AppIcon name="plus" size="12" />
                    {{ t('reservations.add') }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="flex items-center overflow-hidden rounded-md border border-border bg-surface">
                <button type="button" class="flex h-7 w-7 items-center justify-center text-foreground hover:bg-background" :aria-label="t('reservations.previous_day')" @click="shiftDay(-1)">
                    <AppIcon name="chevron-left" size="12" />
                </button>
                <span class="min-w-48 px-3 text-center text-[13px] font-medium text-foreground">{{ dayLabel }}</span>
                <button type="button" class="flex h-7 w-7 items-center justify-center text-foreground hover:bg-background" :aria-label="t('reservations.next_day')" @click="shiftDay(1)">
                    <AppIcon name="chevron-right" size="12" />
                </button>
            </div>
            <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background disabled:opacity-50" :disabled="isToday" @click="load(new Date())">
                {{ t('reservations.today') }}
            </button>

            <dl class="ml-auto flex flex-wrap items-center gap-3 text-[13px] text-muted">
                <div><dt class="inline">{{ t('reservations.summary_booked') }}</dt> <dd class="inline font-semibold tabular-nums text-foreground">{{ summary.booked }}</dd></div>
                <div><dt class="inline">{{ t('reservations.summary_seated') }}</dt> <dd class="inline font-semibold tabular-nums text-foreground">{{ summary.seated }}</dd></div>
                <div><dt class="inline">{{ t('reservations.summary_guests') }}</dt> <dd class="inline font-semibold tabular-nums text-foreground">{{ summary.guests }}</dd></div>
                <div v-if="summary.held > 0"><dt class="inline">{{ t('reservations.deposit') }}</dt> <dd class="inline font-semibold tabular-nums text-foreground">{{ formatMoney(summary.held) }}</dd></div>
            </dl>
        </div>

        <div v-if="reservations.length === 0" class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-border bg-surface py-12 text-center">
            <AppIcon name="calendar-days" size="24" class="text-muted" />
            <p class="text-[13px] font-medium text-foreground">{{ t('reservations.empty') }}</p>
            <p class="text-xs text-muted">{{ t('reservations.empty_hint') }}</p>
        </div>

        <ul v-else class="space-y-2">
            <li v-for="reservation in reservations" :key="reservation.id" class="rounded-lg border border-border bg-surface p-3" :class="holdingNow(reservation) ? 'border-accent ring-1 ring-accent/40' : ''">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-28 shrink-0">
                        <p class="text-sm font-semibold tabular-nums text-foreground">{{ clock(reservation.starts_at) }}</p>
                        <p class="text-xs tabular-nums text-muted">{{ t('reservations.time_range', { from: clock(reservation.starts_at), to: clock(reservation.ends_at) }) }}</p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-1" :title="t('reservations.seats_total', { count: reservation.seats })">
                        <span v-for="table in reservation.tables" :key="table.id" class="rounded-md border border-border bg-background px-2 py-0.5 text-[13px] font-semibold text-foreground">{{ table.name }}</span>
                        <span class="text-[10px] text-muted">{{ t('reservations.seats_short_total', { count: reservation.seats }) }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-foreground">{{ reservation.guest_name }} <span class="font-normal text-muted">- {{ t('reservations.party_size') }} {{ reservation.party_size }}</span></p>
                        <p class="truncate text-xs text-muted"><span class="font-mono">{{ reservation.booking_no }}</span><span v-if="reservation.guest_mobile"> - {{ reservation.guest_mobile }}</span><span v-if="reservation.note"> - {{ reservation.note }}</span></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <span v-if="holdingNow(reservation)" class="rounded-full bg-accent/15 px-2 py-0.5 text-[11px] font-medium text-primary">{{ t('reservations.holding') }}</span>
                        <span v-if="reservation.preorder.length" class="rounded-full bg-background px-2 py-0.5 text-[11px] font-medium text-foreground ring-1 ring-border" :title="t('reservations.preorder')">
                            {{ t('reservations.preorder_items', { count: reservation.preorder.reduce((sum, line) => sum + line.quantity, 0) }) }} - {{ formatMoney(reservation.preorder_total) }}
                        </span>
                        <span v-if="reservation.deposit.status !== 'none'" class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="DEPOSIT_CLASSES[reservation.deposit.status]" :title="t('reservations.deposit')">
                            {{ formatMoney(reservation.deposit.amount) }} {{ t(`reservations.deposit_${reservation.deposit.status}`) }}
                        </span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="STATUS_CLASSES[reservation.status]">{{ t(`reservations.status_${reservation.status}`) }}</span>
                    </div>
                </div>

                <div v-if="reservation.preorder.length" class="mt-2 flex flex-wrap gap-x-3 gap-y-0.5 border-t border-border pt-2 text-xs text-muted">
                    <span v-for="(line, index) in reservation.preorder" :key="index">{{ line.quantity }} x {{ localized(line, 'name') }}<span v-if="line.variant_name"> ({{ localized({ name: line.variant_name, name_bn: line.variant_name_bn }, 'name') }})</span></span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1 border-t border-border pt-2">
                    <button type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2 text-xs font-medium text-foreground hover:bg-background" @click="printConfirmation(reservation)">
                        <AppIcon name="print" size="11" />
                        {{ t('reservations.print_confirmation') }}
                    </button>

                    <template v-if="canManage">
                        <button v-if="reservation.status === 'booked'" type="button" class="inline-flex h-7 items-center gap-1 rounded-md bg-success px-2 text-xs font-medium text-white hover:opacity-90" @click="act(reservation, 'seat')">
                            <AppIcon name="circle-check" size="11" />
                            {{ reservation.preorder.length ? t('reservations.action_seat_preorder') : t('reservations.action_seat') }}
                        </button>
                        <button v-if="reservation.status === 'seated'" type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2 text-xs font-medium text-foreground hover:bg-background" @click="act(reservation, 'complete')">
                            {{ t('reservations.action_complete') }}
                        </button>

                        <button v-if="reservation.deposit.status === 'none' && holding(reservation)" type="button" class="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-surface px-2 text-xs text-foreground hover:bg-background" @click="openAdvance(reservation)">
                            <AppIcon name="money-bill-wave" size="11" />
                            {{ t('reservations.deposit_collect') }}
                        </button>
                        <template v-if="reservation.deposit.status === 'held' && Number(reservation.deposit.balance) > 0">
                            <span class="px-1 text-xs text-muted">{{ t('reservations.deposit_balance', { amount: formatMoney(reservation.deposit.balance) }) }}</span>
                            <button type="button" class="h-7 rounded-md border border-border px-2 text-xs text-foreground hover:bg-background" @click="closeAdvance(reservation, 'refund')">{{ t('reservations.deposit_refund') }}</button>
                            <button type="button" class="h-7 rounded-md border border-border px-2 text-xs text-muted hover:text-warning" @click="closeAdvance(reservation, 'forfeit')">{{ t('reservations.deposit_forfeit') }}</button>
                        </template>

                        <span class="ml-auto flex items-center gap-1">
                            <button v-if="holding(reservation)" type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('reservations.edit')" @click="openEdit(reservation)">
                                <AppIcon name="pen-to-square" size="12" />
                            </button>
                            <button v-if="reservation.status === 'booked'" type="button" class="h-7 rounded-md border border-border px-2 text-xs text-muted hover:text-warning" @click="act(reservation, 'no-show')">{{ t('reservations.action_no_show') }}</button>
                            <button v-if="reservation.status === 'booked'" type="button" class="h-7 rounded-md border border-border px-2 text-xs text-muted hover:text-danger" @click="act(reservation, 'cancel')">{{ t('common.cancel') }}</button>
                        </span>
                    </template>
                </div>
            </li>
        </ul>

        <!-- Make or change a booking -->
        <SlideOver :open="open" wide :title="editing ? t('reservations.edit') : t('reservations.add')" :subtitle="editing ? editing.booking_no : ''" @close="open = false">
            <form id="reservation-form" novalidate class="@container" @submit.prevent="save">
                <div class="grid gap-5 @2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                    <!-- The booking -->
                    <div class="space-y-4">
                        <section class="space-y-2.5">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('reservations.section_guest') }}</h3>
                            <FormField :label="t('reservations.guest_name')" :error="form.errors.guest_name" required>
                                <template #default="{ id }">
                                    <input :id="id" v-model="form.guest_name" type="text" maxlength="120" autocomplete="off" :class="[inputClass, form.errors.guest_name ? '!border-danger' : '']">
                                </template>
                            </FormField>
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
                                <FormField :label="t('reservations.guest_mobile')" :error="form.errors.guest_mobile">
                                    <template #default="{ id }">
                                        <input :id="id" v-model="form.guest_mobile" type="tel" maxlength="20" inputmode="tel" placeholder="01XXXXXXXXX" :class="inputClass">
                                    </template>
                                </FormField>
                                <FormField :label="t('reservations.party_size')" :error="form.errors.party_size" required>
                                    <template #default="{ id }">
                                        <span class="flex h-7 items-center overflow-hidden rounded-md border border-border bg-surface">
                                            <button type="button" class="flex h-7 w-7 items-center justify-center text-foreground hover:bg-background disabled:opacity-40" :disabled="form.party_size <= 1" :aria-label="t('common.decrease')" @click="stepParty(-1)"><AppIcon name="minus" size="10" /></button>
                                            <input :id="id" v-model.number="form.party_size" type="number" min="1" max="200" class="w-10 border-0 bg-transparent text-center text-[13px] font-semibold tabular-nums text-foreground outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                            <button type="button" class="flex h-7 w-7 items-center justify-center text-foreground hover:bg-background" :aria-label="t('common.increase')" @click="stepParty(1)"><AppIcon name="plus" size="10" /></button>
                                        </span>
                                    </template>
                                </FormField>
                            </div>
                        </section>

                        <section class="space-y-2.5">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('reservations.section_when') }}</h3>
                            <div class="grid grid-cols-2 gap-2">
                                <FormField :label="t('reservations.date')" :error="form.errors.starts_at" required>
                                    <template #default="{ id }">
                                        <input :id="id" v-model="form.date" type="date" :class="inputClass">
                                    </template>
                                </FormField>
                                <FormField :label="t('reservations.start')" required>
                                    <template #default="{ id }">
                                        <input :id="id" v-model="form.time" type="time" :class="inputClass">
                                    </template>
                                </FormField>
                            </div>
                            <div>
                                <p class="mb-1 text-[13px] font-medium text-foreground">{{ t('reservations.duration') }}</p>
                                <div class="flex flex-wrap items-center gap-1" role="radiogroup" :aria-label="t('reservations.duration')">
                                    <button
                                        v-for="minutes in durations"
                                        :key="minutes"
                                        type="button"
                                        role="radio"
                                        :aria-checked="Number(form.duration) === minutes"
                                        class="h-7 rounded-md border px-2.5 text-xs font-medium tabular-nums"
                                        :class="Number(form.duration) === minutes ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-background text-muted hover:text-foreground'"
                                        @click="form.duration = minutes"
                                    >
                                        {{ durationLabel(minutes) }}
                                    </button>
                                    <span v-if="endsAt" class="ml-1 text-xs text-muted">{{ t('reservations.until', { time: clock(endsAt.toISOString()) }) }}</span>
                                </div>
                                <p v-if="form.errors.ends_at" class="mt-1 text-xs text-danger">{{ form.errors.ends_at }}</p>
                            </div>
                        </section>

                        <section>
                            <div class="mb-1 flex items-baseline justify-between gap-2">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('reservations.tables') }}</h3>
                                <span class="text-xs tabular-nums" :class="seatsShort ? 'font-medium text-warning' : 'text-muted'">{{ t('reservations.seats_total', { count: selectedSeats }) }}</span>
                            </div>
                            <div class="grid grid-cols-4 gap-1 sm:grid-cols-5">
                                <button
                                    v-for="table in tables"
                                    :key="table.id"
                                    type="button"
                                    :title="`${table.name}${table.area ? ` - ${table.area}` : ''} - ${t('reservations.seats_short', { count: table.seats })}`"
                                    class="flex h-9 flex-col items-center justify-center rounded-md border leading-none"
                                    :class="form.table_ids.includes(table.id) ? 'border-accent bg-accent/15' : 'border-border bg-background hover:border-accent/60'"
                                    :aria-pressed="form.table_ids.includes(table.id)"
                                    @click="toggleTable(table.id)"
                                >
                                    <span class="text-[13px] font-semibold text-foreground">{{ table.name }}</span>
                                    <span class="mt-0.5 text-[9px] tabular-nums text-muted">{{ table.seats }}</span>
                                </button>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">{{ t('reservations.tables_help') }}</p>
                            <p v-if="seatsShort" class="mt-1 text-xs text-warning">{{ t('reservations.too_few_seats', { seats: selectedSeats, party: form.party_size }) }}</p>
                            <p v-if="form.errors.table_ids || form.errors['table_ids.0']" class="mt-1 text-xs text-danger">{{ form.errors.table_ids ?? form.errors['table_ids.0'] }}</p>
                        </section>

                        <FormField :label="t('reservations.note')" :error="form.errors.note">
                            <template #default="{ id }">
                                <textarea :id="id" v-model="form.note" rows="2" maxlength="200" :placeholder="t('reservations.note_placeholder')" class="w-full resize-none rounded-md border border-border bg-surface px-2 py-1.5 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40" />
                                <span class="mt-0.5 block text-right text-[10px] tabular-nums text-muted">{{ form.note.length }}/200</span>
                            </template>
                        </FormField>

                        <!-- Booking money, only when the booking is made -->
                        <section v-if="editing === null" class="rounded-md border border-border">
                            <label class="flex cursor-pointer items-center gap-2 px-2.5 py-2">
                                <input v-model="wantsAdvance" type="checkbox" class="h-4 w-4 rounded border-border accent-[var(--color-primary)]" @change="form.deposit_amount = wantsAdvance ? form.deposit_amount : ''">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-medium text-foreground">{{ t('reservations.deposit_take_now') }}</span>
                                    <span class="block text-[11px] text-muted">{{ t('reservations.deposit_hint') }}</span>
                                </span>
                            </label>
                            <div v-if="wantsAdvance" class="grid grid-cols-[6rem_minmax(0,1fr)] gap-2 border-t border-border p-2.5">
                                <FormField :label="t('reservations.deposit_amount')" :error="form.errors.deposit_amount">
                                    <template #default="{ id }">
                                        <input :id="id" v-model="form.deposit_amount" type="number" min="0" step="1" :class="[inputClass, 'text-right tabular-nums']">
                                    </template>
                                </FormField>
                                <FormField :label="t('reservations.deposit_method')" :error="form.errors.deposit_method">
                                    <template #default="{ id }">
                                        <select :id="id" v-model="form.deposit_method" :class="inputClass">
                                            <option v-for="method in paymentMethods" :key="method.code" :value="method.code">{{ localized(method, 'name') }}</option>
                                        </select>
                                    </template>
                                </FormField>
                                <div v-if="Number(form.deposit_amount) > 0 && methodNeedsReference(form.deposit_method)" class="col-span-2">
                                    <FormField :label="t('reservations.deposit_reference')" :error="form.errors.deposit_reference" required>
                                        <template #default="{ id }">
                                            <input :id="id" v-model="form.deposit_reference" type="text" maxlength="100" :class="inputClass">
                                        </template>
                                    </FormField>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Food ordered ahead -->
                    <section class="flex min-h-0 flex-col gap-2">
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ t('reservations.preorder') }}</h3>
                            <span v-if="form.items.length" class="text-xs font-semibold tabular-nums text-foreground">{{ t('reservations.preorder_items', { count: form.items.reduce((sum, line) => sum + Number(line.quantity), 0) }) }} - {{ formatMoney(preorderTotal) }}</span>
                        </div>
                        <p class="text-[11px] text-muted">{{ t('reservations.preorder_hint') }}</p>

                        <div class="relative">
                            <AppIcon name="magnifying-glass" size="11" class="pointer-events-none absolute left-2 top-2 text-muted" />
                            <input v-model="menuSearch" type="search" :placeholder="t('reservations.preorder_search')" :aria-label="t('reservations.preorder_search')" :class="[inputClass, 'pl-7']">
                        </div>

                        <div class="thin-scrollbar flex gap-1 overflow-x-auto pb-0.5" role="tablist">
                            <button type="button" role="tab" :aria-selected="menuCategory === null" class="h-6 shrink-0 rounded-full border px-2.5 text-[11px] font-medium" :class="menuCategory === null ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted hover:text-foreground'" @click="menuCategory = null">{{ t('reservations.all_dishes') }}</button>
                            <button v-for="category in menuCategories" :key="category.id" type="button" role="tab" :aria-selected="menuCategory === category.id" class="h-6 shrink-0 rounded-full border px-2.5 text-[11px] font-medium" :class="menuCategory === category.id ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted hover:text-foreground'" @click="menuCategory = category.id">{{ localized(category, 'name') }}</button>
                        </div>

                        <ul class="thin-scrollbar max-h-60 divide-y divide-border overflow-y-auto rounded-md border border-border">
                            <li v-if="visibleDishes.length === 0" class="px-2 py-4 text-center text-xs text-muted">{{ t('reservations.no_dishes') }}</li>
                            <li v-for="item in visibleDishes" :key="item.id" class="flex items-center gap-2 px-2 py-1.5" :class="dishQty(item.id) > 0 ? 'bg-accent/10' : ''">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] text-foreground">{{ localized(item, 'name') }}</span>
                                    <span class="block text-[11px] tabular-nums text-muted">{{ item.variants.length ? `${t('reservations.from')} ` : '' }}{{ formatMoney(item.variants[0]?.price ?? item.base_price, { decimals: 0 }) }}</span>
                                </span>
                                <span v-if="dishQty(item.id) > 0" class="flex items-center overflow-hidden rounded-md border border-border bg-surface">
                                    <button type="button" class="flex h-6 w-6 items-center justify-center text-foreground hover:bg-background" :aria-label="t('common.decrease')" @click="lessDish(item)"><AppIcon name="minus" size="10" /></button>
                                    <span class="w-6 text-center text-xs font-semibold tabular-nums">{{ dishQty(item.id) }}</span>
                                    <button type="button" class="flex h-6 w-6 items-center justify-center bg-accent text-accent-foreground" :aria-label="t('common.increase')" @click="addDish(item)"><AppIcon name="plus" size="10" /></button>
                                </span>
                                <button v-else type="button" class="flex h-6 w-6 items-center justify-center rounded-md border border-border text-foreground hover:border-accent" :aria-label="`${t('reservations.preorder_add')}: ${item.name}`" @click="addDish(item)"><AppIcon name="plus" size="10" /></button>
                            </li>
                        </ul>

                        <div v-if="form.items.length > 0" class="rounded-md border border-border">
                            <p class="border-b border-border px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-muted">{{ t('reservations.on_the_order') }}</p>
                            <ul class="divide-y divide-border">
                                <li v-for="(line, index) in form.items" :key="index" class="space-y-1 px-2 py-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-foreground">{{ localized(menuItem(line.menu_item_id), 'name') }}</span>
                                        <select v-if="menuItem(line.menu_item_id)?.variants.length > 1" v-model.number="line.variant_id" class="h-6 max-w-24 rounded-md border border-border bg-surface px-1 text-[11px] text-foreground" :aria-label="t('reservations.choose_size')">
                                            <option v-for="variant in menuItem(line.menu_item_id).variants" :key="variant.id" :value="variant.id">{{ localized(variant, 'name') }}</option>
                                        </select>
                                        <span class="flex items-center overflow-hidden rounded-md border border-border bg-background">
                                            <button type="button" class="flex h-6 w-6 items-center justify-center text-foreground disabled:opacity-40" :disabled="line.quantity <= 1" :aria-label="t('common.decrease')" @click="line.quantity -= 1"><AppIcon name="minus" size="10" /></button>
                                            <span class="w-5 text-center text-xs font-semibold tabular-nums">{{ line.quantity }}</span>
                                            <button type="button" class="flex h-6 w-6 items-center justify-center text-foreground" :aria-label="t('common.increase')" @click="line.quantity = Math.min(50, line.quantity + 1)"><AppIcon name="plus" size="10" /></button>
                                        </span>
                                        <span class="w-14 shrink-0 text-right text-xs font-semibold tabular-nums text-foreground">{{ formatMoney(lineTotal(line), { decimals: 0 }) }}</span>
                                        <button type="button" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-muted hover:text-danger" :aria-label="t('common.remove')" @click="removeDish(index)"><AppIcon name="xmark" size="11" /></button>
                                    </div>
                                    <div v-if="menuItem(line.menu_item_id)?.add_ons.length" class="flex flex-wrap items-center gap-1">
                                        <button
                                            v-for="addOn in menuItem(line.menu_item_id).add_ons"
                                            :key="addOn.id"
                                            type="button"
                                            class="rounded-full border px-2 py-0.5 text-[10px]"
                                            :class="addOnQty(line, addOn.id) > 0 ? 'border-accent bg-accent/15 text-foreground' : 'border-border text-muted hover:text-foreground'"
                                            :aria-pressed="addOnQty(line, addOn.id) > 0"
                                            @click="setAddOn(line, addOn.id, addOnQty(line, addOn.id) > 0 ? 0 : 1)"
                                        >
                                            {{ localized(addOn, 'name') }} +{{ formatMoney(addOn.price, { decimals: 0 }) }}
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <p v-if="form.errors['items.0.menu_item_id']" class="text-xs text-danger">{{ form.errors['items.0.menu_item_id'] }}</p>
                    </section>
                </div>
            </form>

            <template #footer>
                <p class="mr-auto min-w-0 truncate text-xs text-muted">
                    <template v-if="selectedTables.length">{{ selectedTables.map((table) => table.name).join(', ') }} - </template>{{ t('reservations.summary_line', { guests: form.party_size, items: form.items.reduce((sum, line) => sum + Number(line.quantity), 0), total: formatMoney(preorderTotal) }) }}
                </p>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="open = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="reservation-form" :disabled="form.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                    {{ editing ? t('common.save') : t('reservations.book') }}
                </button>
            </template>
        </SlideOver>

        <!-- Take booking money for a booking that is already made -->
        <SlideOver :open="advanceFor !== null" :title="t('reservations.deposit_collect')" :subtitle="advanceFor?.booking_no ?? ''" @close="advanceFor = null">
            <form id="advance-form" novalidate class="space-y-3" @submit.prevent="saveAdvance">
                <p class="text-xs text-muted">{{ t('reservations.deposit_hint') }}</p>
                <FormField :label="t('reservations.deposit_amount')" :error="advanceForm.errors.deposit_amount" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="advanceForm.deposit_amount" type="number" min="1" step="1" :class="[inputClass, 'text-right tabular-nums']">
                    </template>
                </FormField>
                <FormField :label="t('reservations.deposit_method')" :error="advanceForm.errors.deposit_method" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="advanceForm.deposit_method" :class="inputClass">
                            <option v-for="method in paymentMethods" :key="method.code" :value="method.code">{{ localized(method, 'name') }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField v-if="methodNeedsReference(advanceForm.deposit_method)" :label="t('reservations.deposit_reference')" :error="advanceForm.errors.deposit_reference" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="advanceForm.deposit_reference" type="text" maxlength="100" :class="inputClass">
                    </template>
                </FormField>
            </form>
            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="advanceFor = null">{{ t('common.cancel') }}</button>
                <button type="submit" form="advance-form" :disabled="advanceForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    <AppIcon v-if="advanceForm.processing" name="spinner" size="12" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
