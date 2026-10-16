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

const columns = computed(() => [
    { key: 'employee', label: t('attendance.employee'), align: 'left', sortable: true },
    { key: 'status_label', label: t('attendance.status'), align: 'left' },
    { key: 'clock_in', label: t('attendance.clock_in'), align: 'left' },
    { key: 'clock_out', label: t('attendance.clock_out'), align: 'left' },
    { key: 'hours', label: t('attendance.hours'), align: 'right' },
    { key: 'source_label', label: t('attendance.source'), align: 'left' },
]);

const tableRows = computed(() => props.rows.map((row) => ({
    ...row,
    status_label: t(`attendance.${row.status}`),
    source_label: t(`attendance.source_${row.source}`),
    clock_in: row.clock_in ?? '',
    clock_out: row.clock_out ?? '',
})));

const deviceColumns = computed(() => [
    { key: 'name', label: t('attendance.device_name'), align: 'left' },
    { key: 'serial', label: t('attendance.serial'), align: 'left' },
    { key: 'ip_address', label: t('attendance.ip_address'), align: 'left' },
    { key: 'active_label', label: t('attendance.active'), align: 'left' },
    { key: 'seen_label', label: t('attendance.last_seen'), align: 'left' },
]);

const deviceRows = computed(() => props.devices.map((device) => ({
    ...device,
    serial: device.serial ?? '',
    ip_address: device.ip_address ?? '',
    active_label: device.is_active ? t('attendance.active') : t('attendance.inactive'),
    seen_label: device.last_seen_at ? String(device.last_seen_at).replace('T', ' ').slice(0, 16) : t('attendance.never'),
})));

const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';

function changeDate(event) {
    router.get(route('admin.attendance.index'), { date: event.target.value }, { preserveState: true, preserveScroll: true });
}

function saveAttendance() {
    attendanceForm.work_date = props.date;
    attendanceForm.post(route('admin.attendance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            attendanceForm.reset('clock_in', 'clock_out');
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
        <PageHeader :title="t('attendance.title')" :description="t('attendance.subtitle')" />

        <div v-if="deviceToken" class="mb-3 rounded-md border border-border bg-surface p-3 text-[13px] text-foreground">
            <p>{{ t('attendance.token_once', { name: deviceName }) }}</p>
            <p class="mt-1 break-all font-mono">{{ deviceToken }}</p>
            <button type="button" class="mt-2 inline-flex h-7 items-center rounded-md border border-border px-2 text-[13px]" @click="copyToken">
                {{ t('attendance.copy_token') }}
            </button>
        </div>

        <form class="mb-4 grid grid-cols-2 gap-3 rounded-md border border-border bg-surface p-3 md:grid-cols-6" @submit.prevent="saveAttendance">
            <FormField :label="t('attendance.date')">
                <template #default="{ id }">
                    <input :id="id" :value="date" type="date" :class="inputClass" @change="changeDate">
                </template>
            </FormField>
            <FormField :label="t('attendance.employee')" required :error="attendanceForm.errors.employee_id">
                <template #default="{ id }">
                    <select :id="id" v-model="attendanceForm.employee_id" :class="inputClass">
                        <option value="">{{ t('common.select') }}</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </select>
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
            <div class="flex items-end">
                <button type="submit" :disabled="attendanceForm.processing" class="inline-flex h-7 items-center gap-1.5 whitespace-nowrap rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    <AppIcon v-if="attendanceForm.processing" name="spinner" spin />
                    {{ t('attendance.record') }}
                </button>
            </div>
        </form>

        <DataTable :columns="columns" :rows="tableRows" :empty-title="t('attendance.empty')" :empty-description="t('attendance.empty_hint')" />

        <h2 class="mb-1 mt-6 text-sm font-semibold text-foreground">{{ t('attendance.devices') }}</h2>
        <p class="mb-3 text-[13px] text-muted">{{ t('attendance.device_subtitle') }}</p>

        <form class="mb-3 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="saveDevice">
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
            <FormField :label="t('attendance.active')">
                <template #default="{ id }">
                    <label :for="id" class="inline-flex h-7 items-center gap-2 text-[13px] text-foreground">
                        <input :id="id" v-model="deviceForm.is_active" type="checkbox">
                        {{ t('attendance.active') }}
                    </label>
                </template>
            </FormField>
            <div class="flex items-end">
                <button type="submit" :disabled="deviceForm.processing" class="inline-flex h-7 items-center rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                    {{ t('attendance.add_device') }}
                </button>
            </div>
        </form>

        <DataTable :columns="deviceColumns" :rows="deviceRows" :empty-title="t('attendance.devices')" :empty-description="t('attendance.device_subtitle')">
            <template #cell(seen_label)="{ row }">
                <span>{{ row.seen_label }}</span>
                <button type="button" class="ml-2 h-7 rounded-md border border-border px-2 text-[13px]" @click="rotateToken(row)">
                    {{ t('attendance.rotate_token') }}
                </button>
            </template>
        </DataTable>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
