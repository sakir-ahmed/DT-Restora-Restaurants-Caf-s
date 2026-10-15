<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DataTable from '@/components/ui-kit/DataTable.vue';
import DateText from '@/components/ui-kit/DateText.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import MoneyInput from '@/components/ui-kit/MoneyInput.vue';
import MobileInput from '@/components/ui-kit/MobileInput.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import StatusBadge from '@/components/ui-kit/StatusBadge.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

defineProps({
    settings: { type: Object, required: true },
    lookup: { type: Object, default: null },
    totals: { type: Object, required: true },
});

const settingsForm = useForm({});

const mobile = ref('');

function lookupCustomer() {
    router.get(route('admin.loyalty.index'), { mobile: mobile.value }, { preserveState: true });
}

const adjustForm = useForm({ customer_id: null, points: 1, reason: '' });

function openAdjust(customerId) {
    adjustForm.defaults({ customer_id: customerId, points: 1, reason: '' }).reset();
}

function saveAdjust() {
    adjustForm.post(route('admin.loyalty.adjust'), {
        onSuccess: () => {
            notify.success(t('loyalty.adjusted'));
            lookupCustomer();
        },
    });
}

function saveSettings(settings) {
    router.put(route('admin.loyalty.settings'), settings, {
        onSuccess: () => notify.success(t('loyalty.settings_saved')),
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('loyalty.title')" :description="t('loyalty.subtitle')">
            <template #actions>
                <div class="flex items-center gap-3 text-[13px] text-muted">
                    <span>{{ t('loyalty.customers_with_points') }}: {{ totals.customers_with_points }}</span>
                    <span>{{ t('loyalty.points_earned') }}: {{ totals.points_earned }}</span>
                    <span>{{ t('loyalty.points_redeemed') }}: {{ totals.points_redeemed }}</span>
                    <span>{{ t('loyalty.points_expired') }}: {{ totals.points_expired }}</span>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-3 lg:grid-cols-2">
            <!-- Settings -->
            <section class="rounded-lg border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold text-foreground">{{ t('loyalty.settings_title') }}</h2>

                <form class="mt-3" @submit.prevent="saveSettings(settings)">
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <FormField :label="t('loyalty.earn_rate')" :helper="t('loyalty.earn_rate_helper')">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="settings.earn_rate" type="number" min="0" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums">
                            </template>
                        </FormField>

                        <FormField :label="t('loyalty.point_value')" :helper="t('loyalty.point_value_helper')">
                            <MoneyInput v-model="settings.point_value" />
                        </FormField>

                        <FormField :label="t('loyalty.expiry_days')">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="settings.expiry_days" type="number" min="1" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums">
                            </template>
                        </FormField>

                        <div class="flex items-end pb-1">
                            <SwitchField v-model="settings.is_enabled" :label="t('branches.is_active')" />
                        </div>
                    </div>

                    <button type="submit" class="mt-3 h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground">{{ t('common.save') }}</button>
                </form>
            </section>

            <!-- Customer lookup -->
            <section class="rounded-lg border border-border bg-surface p-4">
                <h2 class="text-sm font-semibold text-foreground">{{ t('loyalty.lookup') }}</h2>

                <div class="mt-3 flex items-end gap-2">
                    <div class="flex-1">
                        <FormField :label="t('staff.mobile')">
                            <MobileInput v-model="mobile" />
                        </FormField>
                    </div>
                    <button type="button" class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground" @click="lookupCustomer">{{ t('common.search') }}</button>
                </div>

                <div v-if="lookup" class="mt-4 border-t border-border pt-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-foreground">{{ lookup.customer.name }}</p>
                            <p class="text-xs text-muted">{{ lookup.customer.mobile }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted">{{ t('loyalty.balance') }}</p>
                            <p class="text-2xl font-bold tabular-nums text-foreground">{{ lookup.balance }}</p>
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div class="grid grid-cols-[100px_1fr] items-end gap-2">
                            <FormField :label="t('loyalty.points')">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="adjustForm.points" type="number" :step="1" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-right text-[13px] tabular-nums">
                                </template>
                            </FormField>
                            <FormField :label="t('common.reason')" required>
                                <template #default="{ id }">
                                    <input :id="id" v-model="adjustForm.reason" type="text" class="h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                                </template>
                            </FormField>
                        </div>
                        <button
                            type="button"
                            :disabled="! adjustForm.reason || adjustForm.points === 0"
                            class="h-7 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-50"
                            @click="openAdjust(lookup.customer.id); saveAdjust()"
                        >
                            {{ t('loyalty.adjust') }}
                        </button>
                    </div>

                    <DataTable
                        :rows="lookup.entries"
                        :columns="[
                            { key: 'created_at', label: t('ui_kit.created'), align: 'left' },
                            { key: 'type', label: t('common.status') },
                            { key: 'points', label: t('loyalty.points'), type: 'number' },
                            { key: 'balance_after', label: t('loyalty.balance'), type: 'number' },
                            { key: 'note', label: t('common.note'), align: 'left', truncate: true },
                        ]"
                        :per-page="10"
                        searchable
                    >
                        <template #cell(created_at)="{ value }">
                            <DateText :value="value" />
                        </template>
                        <template #cell(type)="{ value }">
                            <StatusBadge :status="value === 'earned' ? 'approved' : value === 'redeemed' ? 'pending' : value === 'expired' ? 'inactive' : 'draft'" />
                        </template>
                        <template #cell(points)="{ value }">
                            <span :class="value > 0 ? 'text-success' : 'text-danger'">{{ value > 0 ? '+' : '' }}{{ value }}</span>
                        </template>
                    </DataTable>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
