<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Photos and video links for outlets, dishes and the general gallery.
 * An owner is addressed as "general", "branch:ID" or "item:ID".
 */
class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('media.view');

        $user = $this->staff($request);
        $branches = $user->accessibleBranches();

        $owner = (string) $request->query('owner', $this->canUseGeneral($user) ? 'general' : 'branch:'.$branches->first()?->id);
        [$type, $id] = $this->resolveOwner($user, $owner);

        $media = Media::query()
            ->where('mediable_type', $type)
            ->where('mediable_id', $id)
            ->ordered()
            ->get()
            ->map(fn (Media $entry): array => $entry->toDisplay(app()->getLocale()))
            ->values();

        return Inertia::render('admin/media/Index', [
            'owner' => $owner,
            'media' => $media,
            'branches' => $branches->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])->values(),
            'items' => MenuItem::query()
                ->whereIn('branch_id', $branches->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name'])
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'branch' => $branches->firstWhere('id', $item->branch_id)?->name,
                ])->values(),
            'canManage' => $user->can('media.manage'),
            'canUseGeneral' => $this->canUseGeneral($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('media.manage');

        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:30'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_url' => ['nullable', 'url:https', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Media::parseVideo((string) $value) === null) {
                    $fail(__('media.video_unsupported'));
                }
            }],
            'title' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
        ]);

        if (empty($validated['images']) && empty($validated['video_url'])) {
            throw ValidationException::withMessages(['images' => __('media.nothing_to_add')]);
        }

        [$type, $id] = $this->resolveOwner($this->staff($request), $validated['owner']);

        $next = (int) Media::query()->where('mediable_type', $type)->where('mediable_id', $id)->max('sort_order');

        foreach ($validated['images'] ?? [] as $file) {
            Media::query()->create([
                'mediable_type' => $type,
                'mediable_id' => $id,
                'kind' => Media::KIND_IMAGE,
                'path' => $file->store('media', 'public'),
                'title' => $validated['title'] ?? null,
                'title_bn' => $validated['title_bn'] ?? null,
                'sort_order' => ++$next,
            ]);
        }

        if (! empty($validated['video_url'])) {
            Media::query()->create([
                'mediable_type' => $type,
                'mediable_id' => $id,
                'kind' => Media::KIND_VIDEO,
                'video_url' => $validated['video_url'],
                'title' => $validated['title'] ?? null,
                'title_bn' => $validated['title_bn'] ?? null,
                'sort_order' => ++$next,
            ]);
        }

        return back()->with('success', __('media.added'));
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeEntry($request, $media);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ]);

        $media->update($validated);

        return back()->with('success', __('media.saved'));
    }

    public function move(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeEntry($request, $media);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $siblings = Media::query()
            ->where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id)
            ->ordered()
            ->get()
            ->values();

        $index = $siblings->search(fn (Media $entry): bool => $entry->is($media));
        $swapWith = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);

        if ($index === false || $swapWith === null || ($direction === 'up' && $index === 0)) {
            return back();
        }

        // Re-number the whole list so equal sort orders can never trap an item.
        $reordered = $siblings->all();
        [$reordered[$index], $reordered[$direction === 'up' ? $index - 1 : $index + 1]] = [$swapWith, $media];

        foreach ($reordered as $position => $entry) {
            $entry->update(['sort_order' => $position + 1]);
        }

        return back();
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeEntry($request, $media);

        if ($media->path !== null) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();

        return back()->with('success', __('media.deleted'));
    }

    private function staff(Request $request): User
    {
        /** @var User $user */
        $user = $request->user('staff');

        return $user;
    }

    private function canUseGeneral(User $user): bool
    {
        return $user->branch_id === null;
    }

    private function authorizeEntry(Request $request, Media $media): void
    {
        $this->authorize('media.manage');

        $owner = match ($media->mediable_type) {
            'branch' => 'branch:'.$media->mediable_id,
            'menu_item' => 'item:'.$media->mediable_id,
            default => 'general',
        };

        $this->resolveOwner($this->staff($request), $owner);
    }

    /**
     * Turn an owner key into a morph type and id, aborting when the staff
     * member may not manage that outlet, dish or the general gallery.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveOwner(User $user, string $owner): array
    {
        $allowed = $user->accessibleBranches()->pluck('id')->all();

        if ($owner === 'general') {
            abort_unless($this->canUseGeneral($user), 403);

            return [null, null];
        }

        if (preg_match('/^(branch|item):(\d+)$/', $owner, $match) !== 1) {
            abort(404);
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
}
