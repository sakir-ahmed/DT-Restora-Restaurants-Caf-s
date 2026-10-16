<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->user('customer')) {
            return redirect()->route('web.account');
        }

        return inertia('web/Login', [
            'mobile' => old('mobile', $request->session()->get('otp_mobile')),
            'codeSent' => (bool) $request->session()->get('otp_mobile'),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^(\+?88)?01[3-9]\d{8}$/'],
        ]);

        $mobile = normalize_bd_mobile($validated['mobile']);

        if ($otp->isRateLimited($mobile, (string) $request->ip())) {
            throw ValidationException::withMessages([
                'mobile' => __('api.otp_rate_limited'),
            ]);
        }

        if (! $otp->send($mobile, (string) $request->ip())) {
            throw ValidationException::withMessages([
                'mobile' => __('api.otp_send_failed'),
            ]);
        }

        session(['otp_mobile' => $mobile]);

        return back()->with('success', __('web.otp_sent'));
    }

    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $mobile = normalize_bd_mobile($validated['mobile']);

        if (! $otp->verify($mobile, (string) $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('api.otp_invalid'),
            ]);
        }

        $customer = Customer::query()->firstOrCreate(
            ['mobile' => $mobile],
            ['name' => __('web.customer_default_name'), 'mobile_verified_at' => now()],
        );

        $customer->forceFill(['mobile_verified_at' => $customer->mobile_verified_at ?? now()])->save();

        auth('customer')->login($customer);
        $request->session()->regenerate();

        session()->forget('otp_mobile');

        return redirect()->intended(route('web.account'));
    }

    public function logout(Request $request): RedirectResponse
    {
        auth('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('web.home');
    }
}
