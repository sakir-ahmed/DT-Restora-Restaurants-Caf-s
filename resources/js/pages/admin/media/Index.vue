<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MediaManager from '@/components/admin/MediaManager.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    owner: { type: String, required: true },
    media: { type: Array, required: true },
    branches: { type: Array, required: true },
    items: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    canUseGeneral: { type: Boolean, default: false },
});

const ownerType = ref(props.owner.split(':')[0] === 'item' ? 'item' : (props.owner === 'general' ? 'general' : 'branch'));
const ownerId = ref(props.owner.includes(':') ? Number(props.owner.split(':')[1]) : null);

const ownerKey = computed(() => (ownerType.value === 'general' ? 'general' : (ownerId.value ? `${ownerType.value}:${ownerId.value}` : null)));

watch(ownerType, () => {
    ownerId.value = null;

    if (ownerType.value === 'general') {
        goToOwner();
    }
});

function goToOwner() {
    if (! ownerKey.value) {
        return;
    }

    router.get('/admin/media', { owner: ownerKey.value }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div>
        <PageHeader :title="t('media.title')" :description="t('media.subtitle')" />

        <!-- Owner picker -->
        <section class="mb-4 rounded-xl border border-border bg-surface p-3">
            <div class="flex flex-wrap items-end gap-3">
                <FormField :label="t('media.owner')" for="media-owner-type">
                    <select id="media-owner-type" v-model="ownerType" class="h-8 min-w-44 rounded-md border border-border bg-surface px-2 text-[13px]">
                        <option v-if="canUseGeneral" value="general">{{ t('media.owner_general') }}</option>
                        <option value="branch">{{ t('media.owner_outlet') }}</option>
                        <option value="item">{{ t('media.owner_dish') }}</option>
                    </select>
                </FormField>

                <FormField v-if="ownerType === 'branch'" :label="t('media.owner_outlet')" for="media-owner-branch">
                    <select id="media-owner-branch" v-model="ownerId" class="h-8 min-w-56 rounded-md border border-border bg-surface px-2 text-[13px]" @change="goToOwner">
                        <option :value="null" disabled>{{ t('media.choose_outlet') }}</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                </FormField>

                <FormField v-if="ownerType === 'item'" :label="t('media.owner_dish')" for="media-owner-item">
                    <select id="media-owner-item" v-model="ownerId" class="h-8 min-w-64 rounded-md border border-border bg-surface px-2 text-[13px]" @change="goToOwner">
                        <option :value="null" disabled>{{ t('media.choose_dish') }}</option>
                        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }} ({{ item.branch }})</option>
                    </select>
                </FormField>
            </div>
        </section>

        <MediaManager :owner="owner" :media="media" :can-manage="canManage" />
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
