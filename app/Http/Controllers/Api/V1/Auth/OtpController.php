<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\Controller;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^(\+?88)?01[3-9]\d{8}$/'],
        ]);

        $mobile = normalize_bd_mobile($validated['mobile']);

        $sent = $this->otp->send($mobile, (string) $request->ip());

        if (! $sent) {
            return $this->error('TOO_MANY_REQUESTS', __('api.otp_rate_limited'), 429);
        }

        return $this->success(['mobile' => $mobile], __('api.otp_sent'));
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^(\+?88)?01[3-9]\d{8}$/'],
            'code' => ['required', 'digits:6'],
        ]);

        $mobile = normalize_bd_mobile($validated['mobile']);

        $ok = $this->otp->verify($mobile, (string) $validated['code']);

        if (! $ok) {
            return $this->error('UNAUTHENTICATED', __('api.otp_invalid'), 401);
        }

        return $this->success(['verified' => true, 'mobile' => $mobile], __('api.otp_verified'));
    }
}
