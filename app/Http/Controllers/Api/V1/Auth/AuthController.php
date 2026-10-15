<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        [$login, $ip] = $request->throttleKey();
        $key = sha1('api|'.$login.'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->error('TOO_MANY_REQUESTS', __('api.too_many_attempts'), 429, [
                'retry_after' => RateLimiter::availableIn($key),
            ]);
        }

        $credentials = $request->getCredentials();
        $credentials['is_active'] = true;

        $user = User::query()
            ->where(key($credentials), current($credentials))
            ->first();

        if ($user === null || $user->password === null || ! password_verify((string) $credentials['password'], (string) $user->getAuthPassword())) {
            RateLimiter::hit($key, 60);

            return $this->error('UNAUTHENTICATED', __('auth.failed'), 401);
        }

        RateLimiter::clear($key);

        $token = $user->createToken('staff-login', ['staff']);

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ], __('auth.login_success'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return $this->success(null, __('auth.logout_success'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', __('api.unauthenticated'), 401);
        }

        return $this->success($this->userPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'mobile' => $user->mobile,
            'branch_id' => $user->branch_id,
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
        ];
    }
}
