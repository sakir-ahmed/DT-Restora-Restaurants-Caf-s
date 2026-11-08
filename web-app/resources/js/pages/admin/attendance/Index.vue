<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

const props = defineProps({
    date: { type: String, required: true },
    rows: { type: Array, required: true },
    employees: { type: Array, required: true },
    devices: { type: Array, required: true },
});

const page = usePage();
const deviceToken = computed(() => page.props.flash?.device_token ?? '');
const deviceName = computed(() => page.props.flash?.device_name ?? '');

const recordOpen = ref(false);
const devicesOpen = ref(false);
const statusFilter = ref('all');
const failedPhotos = ref(new Set());

const attendanceForm = useForm({
    employee_id: '',
    work_date: props.date,
    status: 'present',
    clock_in: '',
    clock_out: '',
});

const deviceForm = useForm({
    name: '',
    serial: '',
    ip_address: '',
    is_active: true,
});

const counts = computed(() => {
    const present = props.rows.filter((row) => row.status === 'present').length;
    const absent = props.rows.filter((row) => row.status === 'absent').length;
    const hours = props.rows.reduce((sum, row) => sum + Number(row.hours || 0), 0);

    return {
        total: props.rows.length,
        present,
        absent,
        missing: props.rows.length - present - absent,
        hours,
    };
});

const kpis = computed(() => [
    { key: 'total', label: t('attendance.kpi_total'), value: counts.value.total, icon: 'users', tone: 'text-primary bg-primary/10' },
    { key: 'present', label: t('attendance.present'), value: counts.value.present, icon: 'circle-check', tone: 'text-success bg-success/10' },
    { key: 'absent', label: t('attendance.absent'), value: counts.value.absent, icon: 'circle-xmark', tone: 'text-danger bg-danger/10' },
    { key: 'not_recorded', label: t('attendance.not_recorded'), value: counts.value.missing, icon: 'clock', tone: 'text-warning bg-warning/15' },
    { key: 'hours', label: t('attendance.hours'), value: counts.value.hours.toFixed(1), icon: 'hourglass-half', tone: 'text-muted bg-muted/10' },
]);

const filters = computed(() => [
    { key: 'all', label: t('attendance.filter_all'), count: counts.value.total },
    { key: 'present', label: t('attendance.present'), count: counts.value.present },
    { key: 'absent', label: t('attendance.absent'), count: counts.value.absent },
    { key: 'not_recorded', label: t('attendance.not_recorded'), count: counts.value.missing },
]);

const columns = computed(() => [
    { key: 'employee_code', label: t('attendance.employee_id'), align: 'left', sortable: true, width: '7.5rem' },
    { key: 'employee', label: t('attendance.employee'), align: 'left', sortable: true },
    { key: 'status_label', label: t('attendance.status'), align: 'left' },
    { key: 'clock_in', label: t('attendance.clock_in'), align: 'left' },
    { key: 'clock_out', label: t('attendance.clock_out'), align: 'left' },
    { key: 'hours', label: t('attendance.hours'), align: 'right' },
    { key: 'source_label', label: t('attendance.source'), align: 'left' },
    { key: 'actions', label: '', align: 'right', width: '5rem' },
]);

const tableRows = computed(() => props.rows
    .filter((row) => statusFilter.value === 'all' || row.status === statusFilter.value)
    .map((row) => ({
        ...row,
        status_label: t(`attendance.${row.status}`),
        source_label: row.source ? t(`attendance.source_${row.source}`) : '–',
        clock_in: row.clock_in ?? '–',
        clock_out: row.clock_out ?? '–',
        hours: row.status === 'present' ? row.hours : '–',
    })));

const deviceRows = computed(() => props.devices.map((device) => ({
    ...device,
    seen_label: device.last_seen_at ? String(device.last_seen_at).replace('T', ' ').slice(0, 16) : t('attendance.never'),
})));

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

const STATUS_CLASSES = {
    present: 'bg-success/10 text-success',
    absent: 'bg-danger/10 text-danger',
    not_recorded: 'bg-warning/15 text-warning',
};

const STATUS_ICONS = {
    present: 'circle-check',
    absent: 'circle-xmark',
    not_recorded: 'clock',
};

function initials(name) {
    const words = String(name).trim().split(/\s+/);

    return `${words[0]?.[0] ?? ''}${words.length > 1 ? words[words.length - 1][0] : ''}`.toUpperCase();
}

function changeDate(event) {
    router.get(route('admin.attendance.index'), { date: event.target.value }, { preserveState: true, preserveScroll: true });
}

function openRecord(employeeId = '') {
    attendanceForm.clearErrors();
    attendanceForm.employee_id = employeeId;
    recordOpen.value = true;
}

function saveAttendance() {
    attendanceForm.work_date = props.date;
    attendanceForm.post(route('admin.attendance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            attendanceForm.reset('employee_id', 'clock_in', 'clock_out');
            recordOpen.value = false;
            notify.success(t('attendance.recorded'));
        },
    });
}

function saveDevice() {
    deviceForm.serial = deviceForm.serial || null;
    deviceForm.ip_address = deviceForm.ip_address || null;
    deviceForm.post(route('admin.attendance.devices.store'), {
        preserveScroll: true,
        onSuccess: () => {
            deviceForm.reset();
            deviceForm.is_active = true;
            notify.success(t('attendance.device_created'));
        },
    });
}

function rotateToken(device) {
    router.post(route('admin.attendance.devices.token', device.id), {}, {
        preserveScroll: true,
        onSuccess: () => notify.success(t('attendance.token_rotated')),
    });
}

const copying = ref(false);

async function copyToken() {
    if (! deviceToken.value || copying.value) {
        return;
    }

    copying.value = true;

    try {
        await navigator.clipboard.writeText(deviceToken.value);
        notify.success(t('attendance.token_copied'));
    } finally {
        copying.value = false;
    }
}
</script>

<template>
    <div>
        <PageHeader :title="t('attendance.title')" :description="t('attendance.subtitle')">
            <template #actions>
                <input :value="date" type="date" :aria-label="t('attendance.date')" class="h-7 rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent" @change="changeDate">
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:border-accent" @click="devicesOpen = true">
                    <AppIcon name="gear" size="12" />
                    {{ t('attendance.device_settings') }}
                </button>
                <button type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90" @click="openRecord()">
                    <AppIcon name="plus" size="12" />
                    {{ t('attendance.record') }}
                </button>
            </template>
        </PageHeader>

        <!-- Summary for the chosen day -->
        <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <button
                v-for="kpi in kpis"
                :key="kpi.key"
                type="button"
                class="flex items-center gap-2.5 rounded-lg border border-border bg-surface px-3 py-2 text-left transition-colors hover:border-accent"
                :class="statusFilter === kpi.key ? 'border-accent' : ''"
                :disabled="kpi.key === 'hours'"
                @click="statusFilter = kpi.key === 'total' ? 'all' : kpi.key"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :class="kpi.tone"><AppIcon :name="kpi.icon" size="14" /></span>
                <span class="min-w-0">
                    <span class="block text-lg font-semibold leading-none tabular-nums text-foreground">{{ kpi.value }}</span>
                    <span class="mt-1 block truncate text-xs text-muted">{{ kpi.label }}</span>
                </span>
            </button>
        </div>

        <div class="mb-2 flex flex-wrap items-center gap-1" role="tablist">
            <button
                v-for="filter in filters"
                :key="filter.key"
                type="button"
                role="tab"
                :aria-selected="statusFilter === filter.key"
                class="h-7 rounded-md border px-2.5 text-[13px] font-medium"
                :class="statusFilter === filter.key ? 'border-accent bg-accent/15 text-foreground' : 'border-border bg-surface text-muted hover:text-foreground'"
                @click="statusFilter = filter.key"
            >
                {{ filter.label }} <span class="tabular-nums text-muted">({{ filter.count }})</span>
            </button>
        </div>

        <DataTable
            :columns="columns"
            :rows="tableRows"
            searchable
            :search-placeholder="t('attendance.search_placeholder')"
            :per-page="50"
            :empty-title="t('attendance.empty')"
            :empty-description="t('attendance.empty_hint')"
        >
            <template #cell(employee_code)="{ row }">
                <span class="font-mono text-xs tabular-nums text-muted">{{ row.employee_code }}</span>
            </template>
            <template #cell(employee)="{ row }">
                <div class="flex min-w-44 items-center gap-2.5">
                    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-xs font-semibold text-primary">
                        <img
                            v-if="row.photo_url && ! failedPhotos.has(row.id)"
                            :src="row.photo_url"
                            :alt="row.employee"
                            width="32"
                            height="32"
                            loading="lazy"
                            class="h-full w-full object-cover"
                            @error="failedPhotos.add(row.id)"
                        >
                        <span v-else>{{ initials(row.employee) }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[13px] font-medium text-foreground">{{ row.employee }}</span>
                        <span class="block truncate text-xs text-muted">{{ row.designation }}</span>
                    </span>
                </div>
            </template>
            <template #cell(status_label)="{ row }">
                <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-medium" :class="STATUS_CLASSES[row.status]">
                    <AppIcon :name="STATUS_ICONS[row.status]" size="11" />
                    {{ row.status_label }}
                </span>
            </template>
            <template #cell(actions)="{ row }">
                <button type="button" class="h-6 rounded-md border border-border px-2 text-xs font-medium text-foreground hover:border-accent" @click="openRecord(row.id)">
                    {{ row.status === 'not_recorded' ? t('attendance.mark') : t('common.edit') }}
                </button>
            </template>
        </DataTable>

        <!-- Record attendance -->
        <Teleport to="body">
            <div v-if="recordOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="recordOpen = false" @keydown.esc="recordOpen = false">
                <form class="w-full max-w-lg rounded-xl border border-border bg-surface" role="dialog" aria-modal="true" :aria-label="t('attendance.record')" @submit.prevent="saveAttendance">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                        <h2 class="text-base font-semibold text-foreground">{{ t('attendance.record') }}</h2>
                        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('common.close')" @click="recordOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 p-4">
                        <div class="col-span-2">
                            <FormField :label="t('attendance.employee')" required :error="attendanceForm.errors.employee_id">
                                <template #default="{ id }">
                                    <select :id="id" v-model="attendanceForm.employee_id" :class="inputClass">
                                        <option value="">{{ t('common.select') }}</option>
                                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.device_user_id ? `${employee.device_user_id} - ` : '' }}{{ employee.name }}</option>
                                    </select>
                                </template>
                            </FormField>
                        </div>
                        <FormField :label="t('attendance.date')">
                            <template #default="{ id }">
                                <input :id="id" :value="date" type="date" disabled :class="inputClass">
                            </template>
                        </FormField>
                        <FormField :label="t('attendance.status')" :error="attendanceForm.errors.status">
                            <template #default="{ id }">
                                <select :id="id" v-model="attendanceForm.status" :class="inputClass">
                                    <option value="present">{{ t('attendance.present') }}</option>
                                    <option value="absent">{{ t('attendance.absent') }}</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField :label="t('attendance.clock_in')" :error="attendanceForm.errors.clock_in">
                            <template #default="{ id }">
                                <input :id="id" v-model="attendanceForm.clock_in" type="datetime-local" :disabled="attendanceForm.status === 'absent'" :class="inputClass">
                            </template>
                        </FormField>
                        <FormField :label="t('attendance.clock_out')" :error="attendanceForm.errors.clock_out">
                            <template #default="{ id }">
                                <input :id="id" v-model="attendanceForm.clock_out" type="datetime-local" :disabled="attendanceForm.status === 'absent'" :class="inputClass">
                            </template>
                        </FormField>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border px-4 py-2.5">
                        <button type="button" class="h-7 rounded-md border border-border px-2.5 text-[13px] text-foreground hover:bg-background" @click="recordOpen = false">{{ t('common.cancel') }}</button>
                        <button type="submit" :disabled="attendanceForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                            <AppIcon v-if="attendanceForm.processing" name="spinner" spin />
                            {{ t('attendance.record') }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Attendance device settings -->
        <Teleport to="body">
            <div v-if="devicesOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="devicesOpen = false" @keydown.esc="devicesOpen = false">
                <div class="flex max-h-[88vh] w-full max-w-3xl flex-col rounded-xl border border-border bg-surface" role="dialog" aria-modal="true" :aria-label="t('attendance.device_settings')">
                    <div class="flex items-start justify-between gap-2 border-b border-border px-4 py-2.5">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">{{ t('attendance.device_settings') }}</h2>
                            <p class="text-xs text-muted">{{ t('attendance.device_subtitle') }}</p>
                        </div>
                        <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted hover:bg-background hover:text-foreground" :aria-label="t('common.close')" @click="devicesOpen = false">
                            <AppIcon name="xmark" size="14" />
                        </button>
                    </div>

                    <div class="thin-scrollbar min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                        <div v-if="deviceToken" class="rounded-md border border-warning/50 bg-warning/10 p-3 text-[13px] text-foreground">
                            <p>{{ t('attendance.token_once', { name: deviceName }) }}</p>
                            <p class="mt-1 break-all font-mono text-xs">{{ deviceToken }}</p>
                            <button type="button" class="mt-2 inline-flex h-7 items-center rounded-md border border-border bg-surface px-2 text-[13px]" @click="copyToken">
                                {{ t('attendance.copy_token') }}
                            </button>
                        </div>

                        <section>
                            <h3 class="mb-1.5 text-xs font-semibold text-muted">{{ t('attendance.devices') }}</h3>
                            <div v-if="deviceRows.length" class="divide-y divide-border rounded-md border border-border">
                                <div v-for="device in deviceRows" :key="device.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[13px] font-medium text-foreground">{{ device.name }}</p>
                                        <p class="truncate text-xs text-muted">{{ device.serial || '–' }} · {{ device.ip_address || '–' }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium" :class="device.is_active ? 'bg-success/10 text-success' : 'bg-muted/10 text-muted'">
                                        <AppIcon :name="device.is_active ? 'circle-check' : 'circle-xmark'" size="11" />
                                        {{ device.is_active ? t('attendance.active') : t('attendance.inactive') }}
                                    </span>
                                    <span class="text-xs text-muted">{{ t('attendance.last_seen') }}: <span class="tabular-nums text-foreground">{{ device.seen_label }}</span></span>
                                    <button type="button" class="h-6 rounded-md border border-border px-2 text-xs font-medium text-foreground hover:border-accent" @click="rotateToken(device)">
                                        {{ t('attendance.rotate_token') }}
                                    </button>
                                </div>
                            </div>
                            <p v-else class="rounded-md border border-dashed border-border px-3 py-4 text-center text-[13px] text-muted">{{ t('attendance.no_devices') }}</p>
                        </section>

                        <section>
                            <h3 class="mb-1.5 text-xs font-semibold text-muted">{{ t('attendance.add_device') }}</h3>
                            <form class="grid grid-cols-2 gap-3 md:grid-cols-4" @submit.prevent="saveDevice">
                                <FormField :label="t('attendance.device_name')" required :error="deviceForm.errors.name">
                                    <template #default="{ id }">
                                        <input :id="id" v-model="deviceForm.name" type="text" :class="inputClass">
                                    </template>
                                </FormField>
                                <FormField :label="t('attendance.serial')" :error="deviceForm.errors.serial">
                                    <template #default="{ id }">
                                        <input :id="id" v-model="deviceForm.serial" type="text" :class="inputClass">
                                    </template>
                                </FormField>
                                <FormField :label="t('attendance.ip_address')" :error="deviceForm.errors.ip_address">
                                    <template #default="{ id }">
                                        <input :id="id" v-model="deviceForm.ip_address" type="text" :class="inputClass">
                                    </template>
                                </FormField>
                                <div class="flex items-end gap-3">
                                    <label class="inline-flex h-7 items-center gap-2 text-[13px] text-foreground">
                                        <input v-model="deviceForm.is_active" type="checkbox">
                                        {{ t('attendance.active') }}
                                    </label>
                                    <button type="submit" :disabled="deviceForm.processing" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                                        {{ t('attendance.add_device') }}
                                    </button>
                                </div>
                            </form>
                        </section>
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
