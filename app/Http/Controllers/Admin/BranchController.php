<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Models\Branch;
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
            'branches' => $branches,
            'canManage' => $request->user('staff')?->can('branches.manage'),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::query()->create($request->validated());

        return back()->with('success', __('branches.created'));
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        $branch->update($request->validated());

        activity()->performedOn($branch)->causedBy($request->user('staff'))->log('branch.updated');

        return back()->with('success', __('branches.updated'));
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        if ($branch->menuItems()->exists() || $branch->users()->exists()) {
            return back()->with('error', __('branches.delete_not_empty'));
        }

        activity()->performedOn($branch)->causedBy($request->user('staff'))->log('branch.deleted');

        $branch->delete();

        return back()->with('success', __('branches.deleted'));
    }

    private function authorizeBranchAccess(Request $request, Branch $branch): void
    {
        $user = $request->user('staff');

        abort_unless($user?->canAccessBranch($branch->id), 404);
    }
}
