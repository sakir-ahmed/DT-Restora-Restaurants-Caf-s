<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $this->authorize('blog.manage');

        return Inertia::render('admin/blog/Index', [
            'posts' => BlogPost::query()->latest('id')->get()->map(fn (BlogPost $post): array => [
                ...$post->only([
                    'id', 'slug', 'title', 'title_bn', 'category', 'excerpt', 'excerpt_bn',
                    'body', 'body_bn', 'cover_alt', 'status',
                ]),
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'cover' => $post->coverSrc(),
                'is_live' => $post->isPublic(),
                'is_scheduled' => $post->status === BlogPost::STATUS_PUBLISHED && $post->published_at?->isFuture(),
                'url' => $post->url(),
            ])->values(),
            'categories' => BlogPost::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('blog.manage');

        $data = $this->validated($request);

        $post = BlogPost::query()->create([
            ...$data['fields'],
            'slug' => $data['slug'] ?? BlogPost::uniqueSlug($data['fields']['title']),
            'author_id' => $request->user('staff')?->id,
            'cover_path' => $request->file('cover')?->store('blog', 'public'),
        ]);

        return back()->with('success', $post->isPublic() ? __('blog.published') : __('blog.saved'));
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('blog.manage');

        $data = $this->validated($request, $post);
        $attributes = [...$data['fields']];

        if ($data['slug'] !== null) {
            $attributes['slug'] = $data['slug'];
        }

        if ($request->hasFile('cover')) {
            $this->deleteCover($post);
            $attributes['cover_path'] = $request->file('cover')->store('blog', 'public');
        } elseif ($request->boolean('remove_cover')) {
            $this->deleteCover($post);
            $attributes['cover_path'] = null;
        }

        $post->update($attributes);

        return back()->with('success', $post->isPublic() ? __('blog.published') : __('blog.saved'));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->authorize('blog.manage');

        $this->deleteCover($post);
        $post->delete();

        return back()->with('success', __('blog.deleted'));
    }

    private function deleteCover(BlogPost $post): void
    {
        if ($post->cover_path !== null) {
            Storage::disk('public')->delete($post->cover_path);
        }
    }

    /**
     * @return array{fields: array<string, mixed>, slug: string|null}
     */
    private function validated(Request $request, ?BlogPost $post = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'category' => ['nullable', 'string', 'max:60'],
            'excerpt' => ['required', 'string', 'max:300'],
            'excerpt_bn' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:60000'],
            'body_bn' => ['nullable', 'string', 'max:60000'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_alt' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in([BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
        ], [
            'slug.regex' => __('blog.slug_format'),
            'slug.unique' => __('blog.slug_taken'),
        ]);

        $slug = $validated['slug'] ?? null;
        unset($validated['slug'], $validated['cover']);

        // A published post with no date goes live now; a draft never carries a go-live date.
        if ($validated['status'] === BlogPost::STATUS_PUBLISHED) {
            $validated['published_at'] ??= $post->published_at ?? now();
        } else {
            $validated['published_at'] = null;
        }

        return ['fields' => $validated, 'slug' => $slug === '' ? null : $slug];
    }
}
