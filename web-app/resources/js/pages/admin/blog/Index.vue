<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui-kit/EmptyState.vue';
import FormField from '@/components/ui-kit/FormField.vue';
import PageHeader from '@/components/ui-kit/PageHeader.vue';
import SlideOver from '@/components/ui-kit/SlideOver.vue';
import { useConfirm } from '@/components/ui-kit/useConfirm.js';
import { t } from '@/lib/i18n';
import { notify } from '@/lib/notify';

defineProps({
    posts: { type: Array, required: true },
    categories: { type: Array, default: () => [] },
});

const confirm = useConfirm();

const open = ref(false);
const editing = ref(null);
const fileInput = ref(null);
const coverPreview = ref(null);

const blank = {
    title: '', title_bn: '', slug: '', category: '', excerpt: '', excerpt_bn: '',
    body: '', body_bn: '', cover: null, cover_alt: '', remove_cover: false,
    status: 'draft', published_at: '',
};

const form = useForm({ ...blank });

function resetFile() {
    coverPreview.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function create() {
    editing.value = null;
    form.defaults({ ...blank }).reset();
    form.clearErrors();
    resetFile();
    open.value = true;
}

function edit(post) {
    editing.value = post;
    form.defaults({
        title: post.title,
        title_bn: post.title_bn ?? '',
        slug: post.slug,
        category: post.category ?? '',
        excerpt: post.excerpt,
        excerpt_bn: post.excerpt_bn ?? '',
        body: post.body,
        body_bn: post.body_bn ?? '',
        cover: null,
        cover_alt: post.cover_alt ?? '',
        remove_cover: false,
        status: post.status,
        published_at: post.published_at ?? '',
    }).reset();
    form.clearErrors();
    resetFile();
    open.value = true;
}

function onCover(event) {
    const file = event.target.files?.[0] ?? null;

    form.cover = file;
    form.remove_cover = false;
    coverPreview.value = file ? URL.createObjectURL(file) : null;
}

function save() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            notify.success(form.status === 'published' ? t('blog.published') : t('blog.saved'));
        },
        onError: (errors) => notify.error(Object.values(errors)[0]),
    };

    if (editing.value) {
        // A multipart PUT is not read by PHP, so send a POST that Laravel treats as PUT.
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/blog/${editing.value.id}`, options);
    } else {
        form.transform((data) => data).post('/admin/blog', options);
    }
}

async function remove(post) {
    const result = await confirm({
        variant: 'danger',
        level: 'simple',
        title: t('blog.delete_title'),
        message: t('blog.delete_message'),
        confirmText: t('blog.delete'),
    });

    if (result.confirmed) {
        router.delete(`/admin/blog/${post.id}`, { preserveScroll: true, onSuccess: () => notify.success(t('blog.deleted')) });
    }
}

const input = (key) => ['h-8 w-full rounded-md border bg-surface px-2 text-[13px] outline-none focus:ring-2 focus:ring-accent/40', form.errors[key] ? 'border-danger' : 'border-border focus:border-accent'];
const area = (key, rows) => ['w-full rounded-md border bg-surface px-2 py-1.5 font-mono text-[13px] leading-relaxed outline-none focus:ring-2 focus:ring-accent/40', form.errors[key] ? 'border-danger' : 'border-border focus:border-accent'];
</script>

<template>
    <div>
        <PageHeader :title="t('blog.title')" :description="t('blog.subtitle')">
            <template #actions>
                <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground" @click="create">
                    <AppIcon name="plus" size="12" />
                    {{ t('blog.new_post') }}
                </button>
            </template>
        </PageHeader>

        <EmptyState v-if="posts.length === 0" icon="newspaper" :title="t('blog.empty')" />

        <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface">
            <table class="w-full min-w-[40rem] text-left text-[13px]">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wider text-muted">
                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('blog.post_title') }}</th>
                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('blog.category') }}</th>
                        <th scope="col" class="px-3 py-2 font-semibold">{{ t('blog.status') }}</th>
                        <th scope="col" class="w-28 px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="post in posts" :key="post.id" class="border-b border-border/60 last:border-0">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2.5">
                                <img v-if="post.cover" :src="post.cover" alt="" class="h-9 w-14 shrink-0 rounded object-cover">
                                <span v-else class="flex h-9 w-14 shrink-0 items-center justify-center rounded bg-background text-muted"><AppIcon name="newspaper" size="13" /></span>
                                <div class="min-w-0">
                                    <button type="button" class="block max-w-xs truncate text-left font-medium hover:underline" @click="edit(post)">{{ post.title }}</button>
                                    <span class="block truncate text-xs text-muted">/blog/{{ post.slug }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-muted">{{ post.category ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium" :class="post.is_live ? 'bg-success/10 text-success' : (post.is_scheduled ? 'bg-warning/15 text-warning' : 'bg-muted/10 text-muted')">
                                {{ post.is_live ? t('blog.status_live') : (post.is_scheduled ? t('blog.status_scheduled') : t('blog.status_draft')) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex justify-end gap-1">
                                <a v-if="post.is_live" :href="post.url" target="_blank" rel="noopener" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('blog.view')" :title="t('blog.view')"><AppIcon name="arrow-up-right" size="11" /></a>
                                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-muted hover:text-foreground" :aria-label="t('blog.edit')" @click="edit(post)"><AppIcon name="pen-to-square" size="11" /></button>
                                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-md border border-border text-danger hover:bg-danger/10" :aria-label="t('blog.delete')" @click="remove(post)"><AppIcon name="trash" size="11" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="open" :title="editing ? t('blog.edit_post') : t('blog.new_post')" wide @close="open = false">
            <form novalidate class="space-y-3" @submit.prevent="save">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('blog.post_title')" required for="blog-title" :error="form.errors.title">
                        <input id="blog-title" v-model="form.title" type="text" maxlength="180" :class="input('title')">
                    </FormField>
                    <FormField :label="t('blog.post_title_bn')" for="blog-title-bn" :error="form.errors.title_bn">
                        <input id="blog-title-bn" v-model="form.title_bn" type="text" maxlength="180" :class="input('title_bn')">
                    </FormField>
                    <FormField :label="t('blog.category')" for="blog-category" :helper="t('blog.category_hint')" :error="form.errors.category">
                        <input id="blog-category" v-model="form.category" type="text" maxlength="60" list="blog-categories" :class="input('category')">
                        <datalist id="blog-categories"><option v-for="category in categories" :key="category" :value="category" /></datalist>
                    </FormField>
                    <FormField :label="t('blog.slug')" for="blog-slug" :helper="t('blog.slug_hint')" :error="form.errors.slug">
                        <input id="blog-slug" v-model="form.slug" type="text" maxlength="160" placeholder="my-first-post" :class="input('slug')">
                    </FormField>
                </div>

                <FormField :label="t('blog.excerpt')" required for="blog-excerpt" :helper="t('blog.excerpt_hint')" :error="form.errors.excerpt">
                    <textarea id="blog-excerpt" v-model="form.excerpt" rows="2" maxlength="300" :class="area('excerpt')" class="font-sans" />
                </FormField>
                <FormField :label="t('blog.excerpt_bn')" for="blog-excerpt-bn" :error="form.errors.excerpt_bn">
                    <textarea id="blog-excerpt-bn" v-model="form.excerpt_bn" rows="2" maxlength="300" :class="area('excerpt_bn')" class="font-sans" />
                </FormField>

                <FormField :label="t('blog.body')" required for="blog-body" :helper="t('blog.body_hint')" :error="form.errors.body">
                    <textarea id="blog-body" v-model="form.body" rows="12" :class="area('body')" />
                </FormField>
                <FormField :label="t('blog.body_bn')" for="blog-body-bn" :error="form.errors.body_bn">
                    <textarea id="blog-body-bn" v-model="form.body_bn" rows="10" :class="area('body_bn')" />
                </FormField>

                <!-- Cover photo -->
                <div class="rounded-xl border border-border p-3">
                    <FormField :label="t('blog.cover')" for="blog-cover" :helper="t('blog.cover_hint')" :error="form.errors.cover">
                        <input id="blog-cover" ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-[13px] file:mr-3 file:h-8 file:rounded-md file:border file:border-border file:bg-background file:px-3 file:text-[13px]" @change="onCover">
                    </FormField>

                    <img v-if="coverPreview || (editing?.cover && ! form.remove_cover)" :src="coverPreview ?? editing.cover" alt="" class="mt-2 aspect-[16/9] w-full max-w-xs rounded-lg border border-border object-cover">

                    <label v-if="editing?.cover && ! coverPreview" class="mt-2 flex items-center gap-2 text-[13px]">
                        <input v-model="form.remove_cover" type="checkbox" class="h-4 w-4">
                        {{ t('blog.remove_cover') }}
                    </label>

                    <FormField class="mt-2" :label="t('blog.cover_alt')" for="blog-cover-alt" :error="form.errors.cover_alt">
                        <input id="blog-cover-alt" v-model="form.cover_alt" type="text" maxlength="150" :class="input('cover_alt')">
                    </FormField>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField :label="t('blog.status')" required for="blog-status" :error="form.errors.status">
                        <select id="blog-status" v-model="form.status" :class="input('status')">
                            <option value="draft">{{ t('blog.status_draft') }}</option>
                            <option value="published">{{ t('blog.status_published') }}</option>
                        </select>
                    </FormField>
                    <FormField v-if="form.status === 'published'" :label="t('blog.published_at')" for="blog-published-at" :helper="t('blog.published_at_hint')" :error="form.errors.published_at">
                        <input id="blog-published-at" v-model="form.published_at" type="datetime-local" :class="input('published_at')">
                    </FormField>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="h-8 rounded-md border border-border px-3 text-[13px]" @click="open = false">{{ t('blog.cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-accent px-3 text-[13px] font-medium text-accent-foreground disabled:opacity-60">
                        <AppIcon v-if="form.processing" name="spinner" size="12" spin />
                        {{ form.processing ? t('blog.saving') : t('blog.save') }}
                    </button>
                </div>
            </form>
        </SlideOver>
    </div>
</template>

<script>
import AdminLayout from '@/layouts/AdminLayout.vue';

export default { layout: AdminLayout };
</script>
