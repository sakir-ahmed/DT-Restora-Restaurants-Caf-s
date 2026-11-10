<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Models\Branch;
use App\Services\PosSettings;
use App\Support\DeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('branches.view');

        $branches = Branch::query()
            ->when($request->user('staff')?->branch_id !== null, fn ($query) => $query->where('id', $request->user('staff')->branch_id))
            ->orderBy('code')
            ->get();

        return Inertia::render('admin/branches/Index', [
            'branches' => $branches->values(),
            'canManage' => $request->user('staff')?->can('branches.manage'),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::query()->create($this->attributes($request));

        return back()->with('success', __('branches.created'));
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        $branch->update($this->attributes($request));

        activity()->performedOn($branch)->causedBy($request->user('staff'))->log('branch.updated');

        return back()->with('success', __('branches.updated'));
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        $blockers = DeletionGuard::blockers([
            __('branches.related_menu_items') => $branch->menuItems(),
            __('branches.related_staff') => $branch->users(),
        ]);

        if ($blockers !== []) {
            return back()->with('error', DeletionGuard::message($branch->name, $blockers));
        }

        activity()->performedOn($branch)->causedBy($request->user('staff'))->log('branch.deleted');

        $branch->delete();

        return back()->with('success', __('branches.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(BranchRequest $request): array
    {
        $validated = $request->validated();
        $pos = $validated['pos'] ?? null;

        unset($validated['pos']);

        if (is_array($pos)) {
            $validated['pos_settings'] = PosSettings::fromInput($pos);
        }

        return $validated;
    }

    private function authorizeBranchAccess(Request $request, Branch $branch): void
    {
        $user = $request->user('staff');

        abort_unless($user?->canAccessBranch($branch->id), 404);
    }
}
