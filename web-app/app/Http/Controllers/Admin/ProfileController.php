<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfilePasswordRequest;
use App\Http\Requests\Admin\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user('staff');

        return Inertia::render('admin/profile/Index', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'mobile' => $user->mobile,
                'locale' => $user->locale ?? app()->getLocale(),
                'avatar_url' => $user->avatar_url,
                'roles' => $user->getRoleNames()->all(),
                'branch' => $user->branch?->name,
            ],
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user('staff');
        $validated = $request->validated();
        $avatarPath = $user->avatar_path;

        if ($request->hasFile('avatar') || $request->boolean('remove_avatar')) {
            if ($avatarPath !== null && str_starts_with($avatarPath, 'avatars/')) {
                Storage::disk('public')->delete($avatarPath);
            }

            $avatarPath = $request->file('avatar')?->store('avatars', 'public');
        }

        if (filled($validated['mobile'] ?? null)) {
            $validated['mobile'] = normalize_bd_mobile((string) $validated['mobile']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'username' => $validated['username'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'locale' => $validated['locale'],
            'avatar_path' => $avatarPath,
        ]);

        session(['locale' => $validated['locale']]);

        return back()->with('success', __('profile.updated'));
    }

    public function updatePassword(ProfilePasswordRequest $request): RedirectResponse
    {
        $user = $request->user('staff');

        $user->update(['password' => $request->validated('password')]);

        activity()->performedOn($user)->causedBy($user)->log('staff.password_changed');

        return back()->with('success', __('profile.password_changed'));
    }
}
