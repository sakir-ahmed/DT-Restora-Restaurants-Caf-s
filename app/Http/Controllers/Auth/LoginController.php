<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        [$login, $ip] = $request->throttleKey();
        $key = sha1($login.'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login' => __('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        $credentials = $request->getCredentials();
        $credentials['is_active'] = true;

        $remember = $request->boolean('remember');

        if (! Auth::guard('staff')->attempt($credentials, $remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        activity()
            ->causedBy($request->user('staff'))
            ->withProperties(['ip' => $ip])
            ->log('auth.login');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('staff')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }
}
