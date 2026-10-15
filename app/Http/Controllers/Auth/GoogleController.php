<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('login')->with('error', __('auth.google_not_configured'));
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('login')->with('error', __('auth.google_not_configured'));
        }

        $googleUser = Socialite::driver('google')->user();

        $user = User::query()
            ->where('email', $googleUser->getEmail())
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return redirect()->route('login')->with('error', __('auth.google_no_account'));
        }

        $user->forceFill(['google_id' => $googleUser->getId()])->save();

        Auth::guard('staff')->login($user);
        session()->regenerate();

        activity()->causedBy($user)->log('auth.login.google');

        return redirect()->intended(route('admin.dashboard'));
    }
}
