<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

/**
 * Photos and video links for one owner ("general", "branch:ID" or "item:ID").
 * Compact list used on the Photos and videos page and inside the dish form.
 * Every action saves straight away; the dish form's own Save does not wait for it.
 */
const props = defineProps({
    owner: { type: String, required: true },
    media: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const confirm = useConfirm();
const keep = { preserveScroll: true, preserveState: true };
const isDishOwner = computed(() => props.owner.startsWith('item:'));

const fileInput = ref(null);
const dragging = ref(false);
const uploading = ref(false);
const busyId = ref(null);

function reportErrors(errors) {
    notify.error(Object.values(errors)[0]);
}

function uploadPhotos(files) {
    const images = Array.from(files ?? []);

    if (! images.length) {
        return;
    }

    uploading.value = true;

    router.post('/admin/media', { owner: props.owner, images }, {
        ...keep,
        forceFormData: true,
        onSuccess: () => notify.success(t('media.added')),
        onError: reportErrors,
        onFinish: () => {
            uploading.value = false;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function onDrop(event) {
    dragging.value = false;
    uploadPhotos(event.dataTransfer?.files);
}

// Video link, added from a small inline row
const videoOpen = ref(false);
const videoForm = useForm({ owner: props.owner, video_url: '', title: '', title_bn: '' });

function addVideo() {
    videoForm.owner = props.owner;

    videoForm.post('/admin/media', {
        ...keep,
        onSuccess: () => {
            notify.success(t('media.added'));
            videoForm.reset();
            videoOpen.value = false;
        },
    });
}

function runOn(entry, action) {
    busyId.value = entry.id;

    return action.finally(() => {
        busyId.value = null;
    });
}

function send(entry, method, url, data, message) {
    return new Promise((resolve) => {
        router[method](url, data, {
            ...keep,
            onSuccess: () => message && notify.success(message),
            onError: reportErrors,
            onFinish: resolve,
        });
    });
}

function makeFeatured(entry) {
    runOn(entry, send(entry, 'post', `/admin/media/${entry.id}/feature`, {}, t('media.featured_set')));
}

function toggleVisible(entry) {
    runOn(entry, send(entry, 'put', `/admin/media/${entry.id}`, {
        title: entry.title_en ?? '',
        title_bn: entry.title_bn ?? '',
        is_active: ! entry.is_active,
    }, t('media.saved')));
}

function move(entry, direction) {
    runOn(entry, send(entry, 'post', `/admin/media/${entry.id}/move`, { direction }));
}

function replaceFile(entry, event) {
    const file = event.target.files?.[0];

    if (! file) {
        return;
    }

    busyId.value = entry.id;

    router.post(`/admin/media/${entry.id}/replace`, { image: file }, {
        ...keep,
        forceFormData: true,
        onSuccess: () => notify.success(t('media.replaced')),
        onError: reportErrors,
        onFinish: () => {
            busyId.value = null;
            event.target.value = '';
        },
    });
}

// Caption editing opens under the row
const editingId = ref(null);
const captionForm = useForm({ title: '', title_bn: '', is_active: true });

function editCaption(entry) {
    editingId.value = editingId.value === entry.id ? null : entry.id;
    captionForm.title = entry.title_en ?? '';
    captionForm.title_bn = entry.title_bn ?? '';
    captionForm.is_active = entry.is_active;
}

function saveCaption(entry) {
    captionForm.put(`/admin/media/${entry.id}`, {
        ...keep,
        onSuccess: () => {
            notify.success(t('media.saved'));
            editingId.value = null;
        },
        onError: reportErrors,
    });
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

const photos = computed(() => props.media.filter((entry) => entry.kind !== 'video'));
const videos = computed(() => props.media.filter((entry) => entry.kind === 'video'));

const sections = computed(() => [
    { key: 'photos', title: t('media.photos_title'), icon: 'image', items: photos.value, empty: t('media.photos_empty') },
    { key: 'videos', title: t('media.videos_title'), icon: 'video', items: videos.value, empty: t('media.videos_empty') },
]);

const button = 'flex h-6 w-6 shrink-0 items-center justify-center rounded border border-transparent text-muted hover:border-border hover:bg-background hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40';
const inputClass = 'h-7 w-full rounded-md border border-border bg-surface px-2 text-[13px] text-foreground outline-none focus:border-accent focus:ring-2 focus:ring-accent/40';
</script>

<template>
    <div class="space-y-4">
        <input v-if="canManage" :id="`media-images-${owner}`" ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" @change="uploadPhotos($event.target.files)">

        <section v-for="section in sections" :key="section.key" class="space-y-2">
            <div class="flex h-7 items-center justify-between gap-2">
                <h4 class="flex items-center gap-2 text-[13px] font-semibold">
                    <AppIcon :name="section.icon" size="12" class="text-muted" />
                    {{ section.title }}
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-md bg-background px-1.5 text-[11px] font-medium text-muted">{{ section.items.length }}</span>
                </h4>

                <template v-if="canManage">
                    <template v-if="section.key === 'photos'">
                        <button type="button" :disabled="uploading" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background disabled:opacity-60" @click="fileInput?.click()">
                            <AppIcon :name="uploading ? 'spinner' : 'plus'" size="11" :spin="uploading" />
                            {{ uploading ? t('media.adding') : t('media.choose_photos') }}
                        </button>
                    </template>
                    <button v-else type="button" class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] font-medium text-foreground hover:bg-background" :aria-expanded="videoOpen" @click="videoOpen = ! videoOpen">
                        <AppIcon name="plus" size="11" />
                        {{ t('media.add_video') }}
                    </button>
                </template>
            </div>

            <form v-if="section.key === 'videos' && videoOpen && canManage" class="space-y-2 rounded-lg border border-border bg-background p-2.5" @submit.prevent="addVideo">
                <div>
                    <label :for="`media-video-url-${owner}`" class="mb-1 block text-xs font-medium">{{ t('media.video_url') }}</label>
                    <input :id="`media-video-url-${owner}`" v-model="videoForm.video_url" type="url" placeholder="https://www.youtube.com/watch?v=" :class="[inputClass, videoForm.errors.video_url ? 'border-danger' : '']">
                    <p v-if="videoForm.errors.video_url" class="mt-1 text-xs text-danger">{{ videoForm.errors.video_url }}</p>
                    <p v-else class="mt-1 text-xs text-muted">{{ t('media.video_hint') }}</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <label :for="`media-video-title-${owner}`" class="mb-1 block text-xs font-medium">{{ t('media.caption') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></label>
                        <input :id="`media-video-title-${owner}`" v-model="videoForm.title" type="text" maxlength="150" :class="inputClass">
                    </div>
                    <div>
                        <label :for="`media-video-title-bn-${owner}`" class="mb-1 block text-xs font-medium">{{ t('media.caption_bn') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></label>
                        <input :id="`media-video-title-bn-${owner}`" v-model="videoForm.title_bn" type="text" maxlength="150" :class="inputClass">
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] hover:bg-background" @click="videoOpen = false">{{ t('common.cancel') }}</button>
                    <button type="submit" :disabled="videoForm.processing || ! videoForm.video_url" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                        <AppIcon v-if="videoForm.processing" name="spinner" size="12" spin />
                        {{ t('media.add') }}
                    </button>
                </div>
            </form>

            <!-- Photos: the empty state doubles as the drop area -->
            <div
                v-if="section.items.length === 0"
                class="rounded-lg border border-dashed px-3 py-4 text-center text-[13px] text-muted transition-colors"
                :class="section.key === 'photos' && dragging ? 'border-accent bg-accent/5' : 'border-border'"
                @dragover.prevent="section.key === 'photos' && (dragging = true)"
                @dragleave.prevent="dragging = false"
                @drop.prevent="section.key === 'photos' && onDrop($event)"
            >
                {{ section.empty }}
            </div>

            <ul
                v-else
                class="divide-y divide-border overflow-hidden rounded-lg border bg-surface transition-colors"
                :class="section.key === 'photos' && dragging ? 'border-accent' : 'border-border'"
                @dragover.prevent="section.key === 'photos' && canManage && (dragging = true)"
                @dragleave.prevent="dragging = false"
                @drop.prevent="section.key === 'photos' && canManage && onDrop($event)"
            >
                <li v-for="(entry, index) in section.items" :key="entry.id" :class="entry.is_active ? '' : 'bg-background'">
                    <div class="flex items-center gap-2.5 px-2.5 py-2">
                        <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-md border border-border bg-background" :class="entry.is_active ? '' : 'opacity-50'">
                            <img v-if="entry.kind === 'image' && entry.src" :src="entry.src" :alt="entry.title ?? ''" loading="lazy" class="h-full w-full object-cover">
                            <img v-else-if="entry.thumbnail" :src="entry.thumbnail" alt="" loading="lazy" class="h-full w-full object-cover">
                            <span v-else class="flex h-full w-full items-center justify-center text-muted"><AppIcon name="video" size="14" /></span>
                            <span v-if="entry.kind === 'video'" class="absolute bottom-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded bg-black/60 text-white"><AppIcon name="play" size="8" /></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-medium" :class="entry.is_active ? '' : 'text-muted'">{{ entry.title || (entry.kind === 'video' ? entry.video_url : t('media.no_caption')) }}</p>
                            <div v-if="entry.is_featured || ! entry.is_active || (entry.kind === 'video' && entry.title)" class="mt-0.5 flex flex-wrap items-center gap-1">
                                <span v-if="entry.is_featured" class="inline-flex h-5 items-center gap-1 rounded-md bg-accent/15 px-1.5 text-[11px] font-medium text-accent"><AppIcon name="star" size="9" />{{ t('media.featured') }}</span>
                                <span v-if="! entry.is_active" class="inline-flex h-5 items-center rounded-md bg-background px-1.5 text-[11px] text-muted">{{ t('media.hidden') }}</span>
                                <span v-if="entry.kind === 'video' && entry.title" class="truncate text-[11px] text-muted">{{ entry.video_url }}</span>
                            </div>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 items-center gap-0.5">
                            <span v-if="busyId === entry.id" class="flex h-6 w-6 items-center justify-center text-muted"><AppIcon name="spinner" size="12" spin /></span>
                            <template v-else>
                                <button v-if="isDishOwner && entry.kind === 'image'" type="button" :class="[button, entry.is_featured ? '!text-accent' : '']" :disabled="entry.is_featured" :title="entry.is_featured ? t('media.featured') : t('media.make_featured')" :aria-label="t('media.make_featured')" @click="makeFeatured(entry)">
                                    <AppIcon name="star" size="12" />
                                </button>
                                <label v-if="entry.kind === 'image'" :class="[button, 'cursor-pointer']" :title="t('media.replace')">
                                    <AppIcon name="rotate" size="12" />
                                    <span class="sr-only">{{ t('media.replace') }}</span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="replaceFile(entry, $event)">
                                </label>
                                <button type="button" :class="[button, editingId === entry.id ? 'border-border bg-background text-foreground' : '']" :title="t('media.edit_caption')" :aria-label="t('media.edit_caption')" @click="editCaption(entry)">
                                    <AppIcon name="pen-to-square" size="12" />
                                </button>
                                <button type="button" :class="button" :title="entry.is_active ? t('media.hide') : t('media.show')" :aria-label="entry.is_active ? t('media.hide') : t('media.show')" @click="toggleVisible(entry)">
                                    <AppIcon :name="entry.is_active ? 'eye' : 'eye-slash'" size="12" />
                                </button>
                                <button type="button" :class="button" :disabled="index === 0" :title="t('media.move_up')" :aria-label="t('media.move_up')" @click="move(entry, 'up')">
                                    <AppIcon name="arrow-up" size="11" />
                                </button>
                                <button type="button" :class="button" :disabled="index === section.items.length - 1" :title="t('media.move_down')" :aria-label="t('media.move_down')" @click="move(entry, 'down')">
                                    <AppIcon name="arrow-down" size="11" />
                                </button>
                                <button type="button" :class="[button, 'hover:!text-danger']" :title="t('media.delete')" :aria-label="t('media.delete')" @click="remove(entry)">
                                    <AppIcon name="trash" size="12" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <form v-if="editingId === entry.id" class="grid gap-2 border-t border-border bg-background px-2.5 py-2 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="saveCaption(entry)">
                        <input v-model="captionForm.title" type="text" maxlength="150" :aria-label="t('media.caption')" :placeholder="t('media.caption')" :class="inputClass">
                        <input v-model="captionForm.title_bn" type="text" maxlength="150" :aria-label="t('media.caption_bn')" :placeholder="t('media.caption_bn')" :class="inputClass">
                        <div class="flex gap-2">
                            <button type="button" class="h-7 rounded-md border border-border bg-surface px-2.5 text-[13px] hover:bg-background" @click="editingId = null">{{ t('common.cancel') }}</button>
                            <button type="submit" :disabled="captionForm.processing" class="inline-flex h-7 items-center gap-1.5 rounded-md bg-primary px-2.5 text-[13px] font-medium text-primary-foreground hover:opacity-90 disabled:opacity-60">
                                <AppIcon v-if="captionForm.processing" name="spinner" size="12" spin />
                                {{ t('common.save') }}
                            </button>
                        </div>
                    </form>
                </li>
            </ul>

            <p v-if="section.key === 'photos' && canManage" class="text-xs text-muted">{{ t('media.images_hint') }}</p>
        </section>
    </div>
</template>
