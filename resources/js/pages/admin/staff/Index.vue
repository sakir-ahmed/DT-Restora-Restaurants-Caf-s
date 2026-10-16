<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { formatDate, formatMoney } from '@/lib/format';
import { notify } from '@/lib/notify';

const props = defineProps({
    employees: { type: Array, required: true },
    users: { type: Array, required: true },
    roles: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    canManageUsers: { type: Boolean, default: false },
});

const confirm = useConfirm();
const tab = ref('employees');

const employeeColumns = computed(() => [
    { key: 'name', label: t('staff.employee'), align: 'left', sortable: true },
    { key: 'designation', label: t('staff.designation'), align: 'left', sortable: true },
    { key: 'mobile', label: t('staff.mobile'), align: 'left' },
    { key: 'salary_display', label: t('staff.pay_rate'), align: 'right', sortable: true, minWidth: '140px' },
    { key: 'joined_at', label: t('staff.joined'), align: 'left' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

function payRate(employee) {
    const basis = employee.pay_basis ?? 'monthly';

    return basis === 'monthly' ? employee.monthly_salary : employee.pay_rate;
}

const employeeRows = computed(() => props.employees.map((employee) => ({
    ...employee,
    salary_display: `${t(`staff.basis_${employee.pay_basis ?? 'monthly'}`)} ${formatMoney(payRate(employee))}`,
    joined_at: employee.joined_at ? formatDate(employee.joined_at) : null,
    sort_salary: Number(payRate(employee)),
})));

const userColumns = computed(() => [
    { key: 'name', label: t('staff.name'), align: 'left', sortable: true },
    { key: 'username', label: t('staff.username'), align: 'left', sortable: true },
    { key: 'roles_display', label: t('staff.roles'), align: 'left' },
    { key: 'is_active', label: t('common.status'), minWidth: '104px' },
]);

const userRows = computed(() => props.users.map((user) => ({ ...user, roles_display: user.roles.join(', ') })));

// Employee dialog
const employeeOpen = ref(false);
const editingEmployeeId = ref(null);

const employeeForm = useForm({
    name: '', name_bn: '', designation: '', mobile: '', pay_basis: 'monthly', pay_rate: '0.00', monthly_salary: '0.00', device_user_id: '', joined_at: '', is_active: true,
});

function openEmployee(employee = null) {
    editingEmployeeId.value = employee?.id ?? null;
    const basis = employee?.pay_basis ?? 'monthly';
    employeeForm.defaults({
        name: employee?.name ?? '',
        name_bn: employee?.name_bn ?? '',
        designation: employee?.designation ?? '',
        mobile: employee?.mobile ?? '',
        pay_basis: basis,
        pay_rate: employee ? payRate(employee) : '0.00',
        monthly_salary: employee?.monthly_salary ?? '0.00',
        device_user_id: employee?.device_user_id ?? '',
        joined_at: employee?.joined_at ?? '',
        is_active: employee?.is_active ?? true,
    }).reset();
    employeeOpen.value = true;
}

function saveEmployee() {
    employeeForm.monthly_salary = employeeForm.pay_basis === 'monthly' ? employeeForm.pay_rate : '0.00';
    employeeForm.device_user_id = employeeForm.device_user_id || null;

    const options = {
        onSuccess: () => {
            employeeOpen.value = false;
            notify.success(editingEmployeeId.value === null ? t('staff.employee_created') : t('staff.employee_updated'));
        },
    };

    if (editingEmployeeId.value === null) {
        employeeForm.post(route('admin.staff.employees.store'), options);
    } else {
        employeeForm.put(route('admin.staff.employees.update', editingEmployeeId.value), options);
    }
}

async function deactivateEmployee(employee) {
    const result = await confirm({
        variant: 'warning',
        level: 'simple',
        title: t('staff.deactivate_title', { name: employee.name }),
        message: t('staff.deactivate_message'),
        confirmText: t('staff.deactivate_confirm'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.staff.employees.destroy', employee.id), {
        onSuccess: () => notify.success(t('staff.employee_deactivated')),
    });
}

// User dialog
const userOpen = ref(false);
const editingUserId = ref(null);

const userForm = useForm({
    name: '', email: '', username: '', mobile: '', password: '', roles: [], is_active: true,
});

function openUser(user = null) {
    editingUserId.value = user?.id ?? null;
    userForm.defaults({
        name: user?.name ?? '',
        email: user?.email ?? '',
        username: user?.username ?? '',
        mobile: user?.mobile ?? '',
        password: '',
        roles: user?.roles ?? ['cashier'],
        is_active: user?.is_active ?? true,
    }).reset();
    userOpen.value = true;
}

function saveUser() {
    const options = {
        onSuccess: () => {
            userOpen.value = false;
            notify.success(editingUserId.value === null ? t('staff.user_created') : t('staff.user_updated'));
        },
    };

    if (editingUserId.value === null) {
        userForm.post(route('admin.staff.users.store'), options);
    } else {
        userForm.put(route('admin.staff.users.update', editingUserId.value), options);
    }
}

async function deactivateUser(user) {
    const result = await confirm({
        variant: 'warning',
        level: 'simple',
        title: t('staff.deactivate_user_title', { name: user.name }),
        message: t('staff.deactivate_user_message'),
        confirmText: t('staff.deactivate_confirm'),
    });

    if (! result.confirmed) {
        return;
    }

    router.delete(route('admin.staff.users.destroy', user.id), {
        onSuccess: () => notify.success(t('staff.user_deactivated')),
        onError: () => notify.error(t('staff.cannot_delete_self')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('staff.title')" :description="t('staff.subtitle')">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90"
                    @click="openEmployee()"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('staff.add_employee') }}
                </button>
                <button
                    v-if="canManageUsers"
                    type="button"
                    class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background"
                    @click="openUser()"
                >
                    <AppIcon name="plus" size="12" />
                    {{ t('staff.add_user') }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-3 flex gap-1 border-b border-border">
            <button
                v-for="key in ['employees', 'users']"
                :key="key"
                type="button"
                class="-mb-px border-b-2 px-3 py-1.5 text-[13px] font-medium"
                :class="tab === key ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'"
                @click="tab = key"
            >
                {{ key === 'employees' ? t('staff.employees_tab') : t('staff.users_tab') }}
            </button>
        </div>

        <DataTable
            v-if="tab === 'employees'"
            :columns="employeeColumns"
            :rows="employeeRows"
            searchable
            :clickable="canManage"
            :empty-title="t('staff.no_employees')"
            :empty-description="t('staff.no_employees_hint')"
            :empty-action="canManage ? t('staff.add_employee') : ''"
            @row-click="canManage && openEmployee($event)"
            @empty-action="openEmployee()"
        >
            <template #cell(name)="{ row }">
                <span class="block truncate font-medium">{{ row.name }}</span>
                <span v-if="row.user" class="block truncate text-xs text-muted">{{ row.user.name }}</span>
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
        </DataTable>

        <DataTable
            v-else
            :columns="userColumns"
            :rows="userRows"
            searchable
            :clickable="canManageUsers"
            :empty-title="t('staff.no_users')"
            :empty-action="canManageUsers ? t('staff.add_user') : ''"
            @row-click="canManageUsers && openUser($event)"
            @empty-action="openUser()"
        >
            <template #cell(name)="{ row }">
                <span class="block truncate font-medium">{{ row.name }}</span>
                <span class="block truncate text-xs text-muted">{{ row.email ?? row.mobile }}</span>
            </template>
            <template #cell(is_active)="{ value }">
                <StatusBadge :status="value ? 'active' : 'inactive'" />
            </template>
        </DataTable>

        <!-- Employee dialog -->
        <SlideOver :open="employeeOpen" :title="editingEmployeeId === null ? t('staff.add_employee') : t('staff.edit_employee')" @close="employeeOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveEmployee">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('staff.name')" required :error="employeeForm.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="employeeForm.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="employeeForm.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('branches.name_bn')" :error="employeeForm.errors.name_bn">
                        <template #default="{ id }">
                            <input :id="id" v-model="employeeForm.name_bn" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.designation')" required :error="employeeForm.errors.designation">
                        <template #default="{ id }">
                            <input :id="id" v-model="employeeForm.designation" type="text" :placeholder="t('staff.designation_helper')" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="employeeForm.errors.designation ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')" :error="employeeForm.errors.mobile">
                        <MobileInput v-model="employeeForm.mobile" />
                    </FormField>

                    <FormField :label="t('staff.pay_basis')" required :error="employeeForm.errors.pay_basis">
                        <template #default="{ id }">
                            <select :id="id" v-model="employeeForm.pay_basis" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                                <option value="monthly">{{ t('staff.basis_monthly') }}</option>
                                <option value="daily">{{ t('staff.basis_daily') }}</option>
                                <option value="hourly">{{ t('staff.basis_hourly') }}</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField :label="t('staff.pay_rate')" required :helper="employeeForm.pay_basis === 'monthly' ? t('staff.salary_helper') : ''" :error="employeeForm.errors.pay_rate || employeeForm.errors.monthly_salary">
                        <MoneyInput v-model="employeeForm.pay_rate" />
                    </FormField>

                    <FormField :label="t('staff.device_user_id')" :helper="t('staff.device_user_helper')" :error="employeeForm.errors.device_user_id">
                        <template #default="{ id }">
                            <input :id="id" v-model="employeeForm.device_user_id" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.joined')" :error="employeeForm.errors.joined_at">
                        <template #default="{ id }">
                            <input :id="id" v-model="employeeForm.joined_at" type="date" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40">
                        </template>
                    </FormField>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="employeeOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="employeeForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="saveEmployee">
                    <AppIcon v-if="employeeForm.processing" name="spinner" spin />
                    {{ t('common.save') }}
                </button>
            </template>
        </SlideOver>

        <!-- User dialog -->
        <SlideOver :open="userOpen" :title="editingUserId === null ? t('staff.add_user') : t('staff.edit_user')" :subtitle="t('staff.user_form_hint')" @close="userOpen = false">
            <form novalidate class="space-y-3" @submit.prevent="saveUser">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <FormField :label="t('staff.name')" required :error="userForm.errors.name">
                        <template #default="{ id }">
                            <input :id="id" v-model="userForm.name" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="userForm.errors.name ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.username')" :helper="t('auth.login_helper')" :error="userForm.errors.username">
                        <template #default="{ id }">
                            <input :id="id" v-model="userForm.username" type="text" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="userForm.errors.username ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.email')" :error="userForm.errors.email">
                        <template #default="{ id }">
                            <input :id="id" v-model="userForm.email" type="email" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="userForm.errors.email ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.mobile')" :error="userForm.errors.mobile">
                        <MobileInput v-model="userForm.mobile" />
                    </FormField>

                    <FormField :label="t('auth.password')" :required="editingUserId === null" :helper="editingUserId === null ? '' : t('staff.password_helper')" :error="userForm.errors.password">
                        <template #default="{ id }">
                            <input :id="id" v-model="userForm.password" type="password" autocomplete="new-password" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="userForm.errors.password ? 'border-danger' : 'border-border focus:border-accent'">
                        </template>
                    </FormField>

                    <FormField :label="t('staff.roles')" required :error="userForm.errors.roles">
                        <template #default="{ id }">
                            <select :id="id" v-model="userForm.roles[0]" class="h-7 w-full rounded-md border bg-surface px-2 text-[13px] text-foreground outline-none focus:ring-2 focus:ring-accent/40" :class="userForm.errors.roles ? 'border-danger' : 'border-border focus:border-accent'">
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                        </template>
                    </FormField>
                </div>
            </form>

            <template #footer>
                <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] text-foreground hover:bg-background" @click="userOpen = false">{{ t('common.cancel') }}</button>
                <button type="button" :disabled="userForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60" @click="saveUser">
                    <AppIcon v-if="userForm.processing" name="spinner" spin />
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
