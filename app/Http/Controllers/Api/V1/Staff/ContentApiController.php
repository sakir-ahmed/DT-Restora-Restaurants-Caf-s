<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Letter;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\LetterheadPdf;
use App\Services\PosSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Website content and outlet set-up that the web admin offers and the mobile
 * app mirrors: blog, careers, letters, media gallery and branches.
 */
class ContentApiController extends Controller
{
    use InteractsWithApi;

    // ---- Blog ----------------------------------------------------------

    public function blogPosts(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'blog.manage');

        return $this->success([
            'posts' => BlogPost::query()->latest('id')->get()->map(fn (BlogPost $post): array => $this->blogPayload($post))->all(),
            'categories' => BlogPost::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all(),
        ]);
    }

    public function storeBlogPost(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'blog.manage');

        $data = $this->validatedBlog($request);

        $post = BlogPost::query()->create([
            ...$data['fields'],
            'slug' => $data['slug'] ?? BlogPost::uniqueSlug($data['fields']['title']),
            'author_id' => $user->id,
        ]);

        return $this->success($this->blogPayload($post), null, 201);
    }

    public function updateBlogPost(Request $request, BlogPost $post): JsonResponse
    {
        $this->permit($this->staffUser($request), 'blog.manage');

        $data = $this->validatedBlog($request, $post);
        $attributes = [...$data['fields']];

        if ($data['slug'] !== null) {
            $attributes['slug'] = $data['slug'];
        }

        $post->update($attributes);

        return $this->success($this->blogPayload($post->refresh()));
    }

    public function destroyBlogPost(Request $request, BlogPost $post): JsonResponse
    {
        $this->permit($this->staffUser($request), 'blog.manage');

        if ($post->cover_path !== null) {
            Storage::disk('public')->delete($post->cover_path);
        }

        $post->delete();

        return $this->success(['deleted' => true]);
    }

    // ---- Careers -------------------------------------------------------

    public function careers(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        return $this->success([
            'openings' => JobOpening::query()->withCount('applications')->orderByDesc('id')->get()->map(fn (JobOpening $opening): array => [
                ...$opening->only([
                    'id', 'branch_id', 'title', 'title_bn', 'department', 'employment_type',
                    'summary', 'summary_bn', 'description', 'description_bn',
                    'requirements', 'requirements_bn', 'is_active',
                ]),
                'closes_on' => $opening->closes_on?->toDateString(),
                'applications_count' => $opening->applications_count,
            ])->all(),
            'applications' => JobApplication::query()->with('opening:id,title')->latest()->limit(200)->get()->map(fn (JobApplication $application): array => [
                'id' => $application->id,
                'name' => $application->name,
                'mobile' => $application->mobile,
                'email' => $application->email,
                'message' => $application->message,
                'status' => $application->status,
                'has_cv' => $application->cv_path !== null,
                'job' => $application->opening?->title,
                'created_at' => $application->created_at?->toIso8601String(),
            ])->all(),
            'types' => JobOpening::TYPES,
            'statuses' => JobApplication::STATUSES,
        ]);
    }

    public function storeOpening(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        $opening = JobOpening::query()->create($this->validatedOpening($request));

        return $this->success($opening, null, 201);
    }

    public function updateOpening(Request $request, JobOpening $opening): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        $opening->update($this->validatedOpening($request));

        return $this->success($opening->refresh());
    }

    public function destroyOpening(Request $request, JobOpening $opening): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        $opening->delete();

        return $this->success(['deleted' => true]);
    }

    public function updateApplication(Request $request, JobApplication $application): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        $application->update($request->validate(['status' => ['required', Rule::in(JobApplication::STATUSES)]]));

        return $this->success(['id' => $application->id, 'status' => $application->status]);
    }

    public function destroyApplication(Request $request, JobApplication $application): JsonResponse
    {
        $this->permit($this->staffUser($request), 'careers.manage');

        if ($application->cv_path !== null) {
            Storage::disk('local')->delete($application->cv_path);
        }

        $application->delete();

        return $this->success(['deleted' => true]);
    }

    // ---- Letters -------------------------------------------------------

    public function letters(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'letters.manage');
        $branch = $this->workingBranch($request, $user);

        $letters = Letter::query()
            ->where('branch_id', $branch->id)
            ->latest('letter_date')
            ->latest('id')
            ->get()
            ->map(fn (Letter $letter): array => $this->letterPayload($letter))
            ->all();

        return $this->success($letters);
    }

    public function storeLetter(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'letters.manage');
        $branch = $this->workingBranch($request, $user);

        $letter = Letter::query()->create([
            ...$this->validatedLetter($request),
            'branch_id' => $branch->id,
            'author_id' => $user->id,
        ]);

        return $this->success($this->letterPayload($letter), null, 201);
    }

    public function updateLetter(Request $request, Letter $letter): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'letters.manage');
        abort_unless($user->canAccessBranch($letter->branch_id), 404);

        $letter->update($this->validatedLetter($request));

        return $this->success($this->letterPayload($letter->refresh()));
    }

    public function destroyLetter(Request $request, Letter $letter): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'letters.manage');
        abort_unless($user->canAccessBranch($letter->branch_id), 404);

        $letter->delete();

        return $this->success(['deleted' => true]);
    }

    public function letterPdf(Request $request, Letter $letter, LetterheadPdf $pdf): Response
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'letters.manage');
        abort_unless($user->canAccessBranch($letter->branch_id), 404);

        return $pdf->stream($letter->branch ?? Branch::query()->findOrFail($letter->branch_id), [
            'letter' => [
                'title' => $letter->title,
                'recipient' => $letter->recipient,
                'subject' => $letter->subject,
                'body' => $letter->body,
                'date' => $letter->letter_date->format('d M Y'),
            ],
        ], 'letter-'.$letter->id.'.pdf');
    }

    // ---- Media ---------------------------------------------------------

    public function media(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'media.view');

        $branches = $user->accessibleBranches();
        $owner = (string) $request->query('owner', $user->branch_id === null ? 'general' : 'branch:'.$branches->first()?->id);
        [$type, $id] = $this->resolveMediaOwner($user, $owner);

        return $this->success([
            'owner' => $owner,
            'media' => Media::query()
                ->where('mediable_type', $type)
                ->where('mediable_id', $id)
                ->ordered()
                ->get()
                ->map(fn (Media $entry): array => $entry->toDisplay(app()->getLocale()))
                ->all(),
            'branches' => $branches->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])->values()->all(),
            'can_use_general' => $user->branch_id === null,
        ]);
    }

    public function storeVideo(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'media.manage');

        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:30'],
            'video_url' => ['required', 'url:https', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Media::parseVideo((string) $value) === null) {
                    $fail(__('media.video_unsupported'));
                }
            }],
            'title' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
        ]);

        [$type, $id] = $this->resolveMediaOwner($user, $validated['owner']);
        $next = (int) Media::query()->where('mediable_type', $type)->where('mediable_id', $id)->max('sort_order');

        $entry = Media::query()->create([
            'mediable_type' => $type,
            'mediable_id' => $id,
            'kind' => Media::KIND_VIDEO,
            'video_url' => $validated['video_url'],
            'title' => $validated['title'] ?? null,
            'title_bn' => $validated['title_bn'] ?? null,
            'sort_order' => $next + 1,
        ]);

        return $this->success($entry->toDisplay(app()->getLocale()), null, 201);
    }

    public function storeImages(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'media.manage');

        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:30'],
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'title' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
        ]);

        [$type, $id] = $this->resolveMediaOwner($user, $validated['owner']);
        $next = (int) Media::query()->where('mediable_type', $type)->where('mediable_id', $id)->max('sort_order');
        $created = [];

        foreach ($validated['images'] as $file) {
            $created[] = Media::query()->create([
                'mediable_type' => $type,
                'mediable_id' => $id,
                'kind' => Media::KIND_IMAGE,
                'path' => $file->store('media', 'public'),
                'title' => $validated['title'] ?? null,
                'title_bn' => $validated['title_bn'] ?? null,
                'sort_order' => ++$next,
            ])->toDisplay(app()->getLocale());
        }

        return $this->success($created, null, 201);
    }

    public function updateMedia(Request $request, Media $media): JsonResponse
    {
        $this->authorizeMedia($request, $media);

        $media->update($request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ]));

        return $this->success($media->refresh()->toDisplay(app()->getLocale()));
    }

    public function moveMedia(Request $request, Media $media): JsonResponse
    {
        $this->authorizeMedia($request, $media);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $siblings = Media::query()
            ->where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id)
            ->ordered()
            ->get()
            ->values();

        $index = $siblings->search(fn (Media $entry): bool => $entry->is($media));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && $target >= 0 && $target < $siblings->count()) {
            $reordered = $siblings->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            foreach ($reordered as $position => $entry) {
                $entry->update(['sort_order' => $position + 1]);
            }
        }

        return $this->success(['moved' => true]);
    }

    public function destroyMedia(Request $request, Media $media): JsonResponse
    {
        $this->authorizeMedia($request, $media);

        if ($media->path !== null) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();

        return $this->success(['deleted' => true]);
    }

    // ---- Branches ------------------------------------------------------

    public function branches(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'branches.view');

        $branches = Branch::query()
            ->when($user->branch_id !== null, fn ($query) => $query->where('id', $user->branch_id))
            ->orderBy('code')
            ->get()
            ->map(fn (Branch $branch): array => [...$branch->toArray(), 'pos' => PosSettings::for($branch)])
            ->all();

        return $this->success($branches);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $this->permit($this->staffUser($request), 'branches.manage');

        $branch = Branch::query()->create($this->branchAttributes($request));

        return $this->success($branch, null, 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'branches.manage');
        abort_unless($user->canAccessBranch($branch->id), 404);

        $branch->update($this->branchAttributes($request, $branch));
        activity()->performedOn($branch)->causedBy($user)->log('branch.updated');

        return $this->success([...$branch->refresh()->toArray(), 'pos' => PosSettings::for($branch)]);
    }

    public function destroyBranch(Request $request, Branch $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'branches.manage');
        abort_unless($user->canAccessBranch($branch->id), 404);

        if ($branch->menuItems()->exists() || $branch->users()->exists()) {
            return $this->error('BRANCH_IN_USE', __('branches.delete_not_empty'), 409);
        }

        activity()->performedOn($branch)->causedBy($user)->log('branch.deleted');
        $branch->delete();

        return $this->success(['deleted' => true]);
    }

    // ---- Helpers -------------------------------------------------------

    private function workingBranch(Request $request, User $user): Branch
    {
        $requested = (int) ($request->input('branch_id') ?: $user->branch_id ?: 0);

        if ($requested > 0) {
            $branch = Branch::query()->find($requested);

            if ($branch !== null && $user->canAccessBranch($branch->id)) {
                return $branch;
            }

            abort(404);
        }

        return Branch::query()->orderBy('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function blogPayload(BlogPost $post): array
    {
        return [
            ...$post->only([
                'id', 'slug', 'title', 'title_bn', 'category', 'excerpt', 'excerpt_bn',
                'body', 'body_bn', 'cover_alt', 'status',
            ]),
            'published_at' => $post->published_at?->toIso8601String(),
            'cover' => $post->coverSrc(),
            'is_live' => $post->isPublic(),
            'url' => $post->url(),
        ];
    }

    /**
     * @return array{fields: array<string, mixed>, slug: string|null}
     */
    private function validatedBlog(Request $request, ?BlogPost $post = null): array
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
            'cover_alt' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in([BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = $validated['slug'] ?? null;
        unset($validated['slug']);

        if ($validated['status'] === BlogPost::STATUS_PUBLISHED) {
            $validated['published_at'] ??= $post?->published_at ?? now();
        } else {
            $validated['published_at'] = null;
        }

        return ['fields' => $validated, 'slug' => $slug === '' ? null : $slug];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOpening(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(JobOpening::TYPES)],
            'summary' => ['required', 'string', 'max:300'],
            'summary_bn' => ['nullable', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:8000'],
            'description_bn' => ['nullable', 'string', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'requirements_bn' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
            'closes_on' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedLetter(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'recipient' => ['nullable', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'letter_date' => ['required', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function letterPayload(Letter $letter): array
    {
        return [
            'id' => $letter->id,
            'title' => $letter->title,
            'recipient' => $letter->recipient,
            'subject' => $letter->subject,
            'body' => $letter->body,
            'letter_date' => $letter->letter_date->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchAttributes(Request $request, ?Branch $branch = null): array
    {
        $validated = $request->validate([
            ...PosSettings::rules(),
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:10', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_bn' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'city_bn' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'vat_percent' => ['required', 'numeric', 'between:0,100'],
            'vat_inclusive' => ['boolean'],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100'],
            'allow_negative_stock' => ['boolean'],
            'online_ordering_enabled' => ['boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $pos = $validated['pos'] ?? null;
        unset($validated['pos']);

        if (is_array($pos)) {
            $validated['pos_settings'] = PosSettings::fromInput($pos);
        }

        return $validated;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveMediaOwner(User $user, string $owner): array
    {
        $allowed = $user->accessibleBranches()->pluck('id')->all();

        if ($owner === 'general') {
            abort_unless($user->branch_id === null, 403);

            return [null, null];
        }

        if (preg_match('/^(branch|item):(\d+)$/', $owner, $match) !== 1) {
            throw ValidationException::withMessages(['owner' => 'Unknown gallery.']);
        }

        $id = (int) $match[2];

        if ($match[1] === 'branch') {
            abort_unless(in_array($id, $allowed, true), 403);

            return ['branch', $id];
        }

        $item = MenuItem::query()->withoutGlobalScopes()->findOrFail($id);
        abort_unless(in_array($item->branch_id, $allowed, true), 403);

        return ['menu_item', $id];
    }

    private function authorizeMedia(Request $request, Media $media): void
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'media.manage');

        $owner = match ($media->mediable_type) {
            'branch' => 'branch:'.$media->mediable_id,
            'menu_item' => 'item:'.$media->mediable_id,
            default => 'general',
        };

        $this->resolveMediaOwner($user, $owner);
    }
}
