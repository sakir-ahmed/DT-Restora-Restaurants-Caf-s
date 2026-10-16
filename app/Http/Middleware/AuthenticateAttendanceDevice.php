<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Attendance\RegisterAttendanceDeviceAction;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAttendanceDevice
{
    public function __construct(private readonly RegisterAttendanceDeviceAction $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = is_string($token) ? $this->devices->findByToken($token) : null;

        if ($device === null) {
            throw new AuthenticationException(__('api.unauthenticated'));
        }

        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
