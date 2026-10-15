<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Login', [
            'demoUsers' => $this->demoUsers(),
        ]);
    }

    public function demo(Request $request): RedirectResponse
    {
        abort_unless($this->demoLoginEnabled(), 404);

        $userId = $request->integer('user_id');

        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->first();

        abort_if($user === null, 404);

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip(), 'demo' => true])
            ->log('auth.login');

        return redirect()->intended(route('admin.dashboard'));
    }

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

    private function demoLoginEnabled(): bool
    {
        return ! app()->isProduction() && (bool) config('app.is_demo');
    }

    /**
     * @return list<array{id: int, name: string, username: string, role: string}>
     */
    private function demoUsers(): array
    {
        if (! $this->demoLoginEnabled()) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => (string) $user->username,
                'role' => $user->roles->pluck('name')->join(', '),
            ])
            ->values()
            ->all();
    }
}
