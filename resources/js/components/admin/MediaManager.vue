<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import SwitchField from '@/components/ui-kit/SwitchField.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * Photos and video links for one owner ("general", "branch:ID" or "item:ID").
 * Used on the Photos and videos page and inside the dish form.
 */
const props = defineProps({
    owner: { type: String, required: true },
    media: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const confirm = useConfirm();
const keep = { preserveScroll: true, preserveState: true };

const photoForm = useForm({ owner: props.owner, images: [], title: '', title_bn: '' });
const videoForm = useForm({ owner: props.owner, video_url: '', title: '', title_bn: '' });
const fileInput = ref(null);
const chosen = ref(0);

function onFiles(event) {
    photoForm.images = Array.from(event.target.files ?? []);
    chosen.value = photoForm.images.length;
}

function uploadPhotos() {
    photoForm.owner = props.owner;

    photoForm.post('/admin/media', {
        ...keep,
        forceFormData: true,
        onSuccess: () => {
            notify.success(t('media.added'));
            photoForm.reset('images', 'title', 'title_bn');
            chosen.value = 0;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function addVideo() {
    videoForm.owner = props.owner;

    videoForm.post('/admin/media', {
        ...keep,
        onSuccess: () => {
            notify.success(t('media.added'));
            videoForm.reset('video_url', 'title', 'title_bn');
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function toggleVisible(entry, value) {
    router.put(`/admin/media/${entry.id}`, {
        title: entry.title_en ?? '',
        title_bn: entry.title_bn ?? '',
        is_active: value,
    }, {
        ...keep,
        onSuccess: () => notify.success(t('media.saved')),
        onError: (errors) => notify.error(Object.values(errors)[0]),
    });
}

function move(entry, direction) {
    router.post(`/admin/media/${entry.id}/move`, { direction }, keep);
}

async function remove(entry) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('media.delete_title'),
        message: t('media.delete_message'),
        confirmText: t('media.delete'),
    });

    if (result.confirmed) {
        router.delete(`/admin/media/${entry.id}`, { ...keep, onSuccess: () => notify.success(t('media.deleted')) });
    }
}
</script>

<template>
    <div class="space-y-3">
        <div v-if="canManage" class="grid gap-3" :class="compact ? '' : 'lg:grid-cols-2'">
            <form class="space-y-3 rounded-xl border border-border bg-surface p-3" @submit.prevent="uploadPhotos">
                <h3 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="upload" size="13" />{{ t('media.upload_title') }}</h3>

                <FormField :label="t('media.images')" :for="`media-images-${owner}`" :helper="t('media.images_hint')" :error="photoForm.errors.images || photoForm.errors['images.0']">
                    <input :id="`media-images-${owner}`" ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-[13px] file:mr-3 file:h-8 file:rounded-md file:border file:border-border file:bg-background file:px-3 file:text-[13px]" @change="onFiles">
                </FormField>

                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('media.caption')" :for="`media-photo-title-${owner}`">
                        <input :id="`media-photo-title-${owner}`" v-model="photoForm.title" type="text" maxlength="150" class="h-8 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                    </FormField>
                    <FormField :label="t('media.caption_bn')" :for="`media-photo-title-bn-${owner}`">
                        <input :id="`media-photo-title-bn-${owner}`" v-model="photoForm.title_bn" type="text" maxlength="150" class="h-8 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                    </FormField>
                </div>

                <button type="submit" :disabled="photoForm.processing || ! chosen" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground disabled:opacity-60">
                    <AppIcon v-if="photoForm.processing" name="spinner" size="12" spin />
                    {{ photoForm.processing ? t('media.adding') : (chosen > 1 ? t('media.add_many', { count: chosen }) : t('media.add')) }}
                </button>
            </form>

            <form class="space-y-3 rounded-xl border border-border bg-surface p-3" @submit.prevent="addVideo">
                <h3 class="flex items-center gap-2 text-sm font-semibold"><AppIcon name="video" size="13" />{{ t('media.video_title') }}</h3>

                <FormField :label="t('media.video_url')" :for="`media-video-url-${owner}`" :helper="t('media.video_hint')" :error="videoForm.errors.video_url">
                    <input :id="`media-video-url-${owner}`" v-model="videoForm.video_url" type="url" placeholder="https://www.youtube.com/watch?v=" class="h-8 w-full rounded-md border bg-surface px-2 text-[13px]" :class="videoForm.errors.video_url ? 'border-danger' : 'border-border'">
                </FormField>

                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('media.caption')" :for="`media-video-title-${owner}`">
                        <input :id="`media-video-title-${owner}`" v-model="videoForm.title" type="text" maxlength="150" class="h-8 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                    </FormField>
                    <FormField :label="t('media.caption_bn')" :for="`media-video-title-bn-${owner}`">
                        <input :id="`media-video-title-bn-${owner}`" v-model="videoForm.title_bn" type="text" maxlength="150" class="h-8 w-full rounded-md border border-border bg-surface px-2 text-[13px]">
                    </FormField>
                </div>

                <button type="submit" :disabled="videoForm.processing || ! videoForm.video_url" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground disabled:opacity-60">
                    <AppIcon v-if="videoForm.processing" name="spinner" size="12" spin />
                    {{ videoForm.processing ? t('media.adding') : t('media.add') }}
                </button>
            </form>
        </div>

        <EmptyState v-if="media.length === 0" icon="images" :title="t('media.empty')" />

        <ul v-else class="grid gap-3" :class="compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'">
            <li v-for="(entry, index) in media" :key="entry.id" class="overflow-hidden rounded-xl border border-border bg-surface" :class="entry.is_active ? '' : 'opacity-70'">
                <div class="relative aspect-video bg-background">
                    <img v-if="entry.kind === 'image' && entry.src" :src="entry.src" :alt="entry.title ?? ''" loading="lazy" class="h-full w-full object-cover">
                    <img v-else-if="entry.thumbnail" :src="entry.thumbnail" alt="" loading="lazy" class="h-full w-full object-cover">
                    <div v-else class="flex h-full w-full items-center justify-center text-muted"><AppIcon name="video" size="24" /></div>

                    <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white">
                        <AppIcon :name="entry.kind === 'video' ? 'play' : 'image'" size="10" />
                        {{ entry.kind === 'video' ? t('media.kind_video') : t('media.kind_image') }}
                    </span>
                </div>

                <div class="space-y-2 p-2.5">
                    <p class="truncate text-[13px] font-medium">{{ entry.title || (entry.video_url ?? '') }}</p>

                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-2">
                        <SwitchField :model-value="entry.is_active" :disabled="! canManage" :label="t('media.visible')" @update:model-value="toggleVisible(entry, $event)" />

                        <div v-if="canManage" class="flex shrink-0 items-center gap-1">
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === 0" :aria-label="t('media.move_up')" @click="move(entry, 'up')">
                                <AppIcon name="chevron-left" size="11" />
                            </button>
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground disabled:opacity-40" :disabled="index === media.length - 1" :aria-label="t('media.move_down')" @click="move(entry, 'down')">
                                <AppIcon name="chevron-right" size="11" />
                            </button>
                            <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-danger hover:bg-danger/10" :aria-label="t('media.delete')" @click="remove(entry)">
                                <AppIcon name="trash" size="11" />
                            </button>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
