<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->validatedCredentials(),
            'password' => Hash::make((string) $request->validated('password')),
            'branch_id' => $this->resolvedBranchId($request),
            'is_active' => (bool) $request->boolean('is_active', true),
        ]);

        $user->syncRoles($request->validated('roles'));

        activity()->performedOn($user)->causedBy($request->user('staff'))->log('staff.user_created');

        return back()->with('success', __('staff.user_created'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $credentials = $request->validatedCredentials();

        if (array_key_exists('password', $credentials)) {
            $credentials['password'] = Hash::make((string) $credentials['password']);
        }

        if ($user->id === $request->user('staff')->id
            && in_array('super-admin', $request->validated('roles'), true) === false) {
            throw ValidationException::withMessages([
                'roles' => __('staff.cannot_remove_own_super_admin'),
            ]);
        }

        $user->update([
            ...$credentials,
            'branch_id' => $this->resolvedBranchId($request, $user),
        ]);

        $user->syncRoles($request->validated('roles'));

        activity()->performedOn($user)->causedBy($request->user('staff'))->log('staff.user_updated');

        return back()->with('success', __('staff.user_updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('staff.manage');

        if ($user->id === $request->user('staff')->id) {
            return back()->with('error', __('staff.cannot_delete_self'));
        }

        $user->update(['is_active' => false]);

        activity()->performedOn($user)->causedBy($request->user('staff'))->log('staff.user_deactivated');

        return back()->with('success', __('staff.user_deactivated'));
    }

    /**
     * Branch-scoped roles stay in the acting branch; owner, super-admin
     * and accountant accounts keep the all-branches assignment.
     */
    private function resolvedBranchId(Request $request, ?User $existing = null): ?int
    {
        $actingUser = $request->user('staff');

        if ($actingUser?->branch_id !== null) {
            return $actingUser->branch_id;
        }

        $roles = (array) $request->input('roles', []);

        if (array_intersect($roles, ['owner', 'super-admin', 'accountant']) !== [] && $request->integer('branch_id', 0) === 0) {
            return null;
        }

        $branchId = $request->integer('branch_id', 0);

        if ($branchId > 0 && Branch::query()->whereKey($branchId)->exists()) {
            return $branchId;
        }

        return $existing?->branch_id;
    }
}
