<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class CustomerGoogleController extends Controller
{
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('web.customer.login')->with('error', __('auth.google_not_configured'));
        }

        return $this->google()->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('web.customer.login')->with('error', __('auth.google_not_configured'));
        }

        $googleUser = $this->google()->user();

        $email = $googleUser->getEmail();

        $customer = Customer::query()
            ->where('google_id', $googleUser->getId())
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($customer === null) {
            $customer = Customer::query()->create([
                'name' => $googleUser->getName() ?: __('web.google_customer'),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        } else {
            $customer->forceFill([
                'google_id' => $googleUser->getId(),
                'email' => $customer->email ?: $email,
            ])->save();
        }

        if (! $customer->is_active) {
            return redirect()->route('web.customer.login')->with('error', __('auth.inactive'));
        }

        Auth::guard('customer')->login($customer);
        session()->regenerate();

        return redirect()->intended(route('web.account'));
    }

    private function google(): AbstractProvider
    {
        $driver = Socialite::driver('google');

        if (! $driver instanceof AbstractProvider) {
            abort(503, __('auth.google_not_configured'));
        }

        return $driver->redirectUrl(route('web.customer.google.callback'));
    }
}
