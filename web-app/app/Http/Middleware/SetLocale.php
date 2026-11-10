<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user('staff');

        $locale = match (true) {
            $user?->locale === 'bn' || $user?->locale === 'en' => $user->locale,
            Session::has('locale') => (string) Session::get('locale'),
            $request->query('lang') === 'bn' => 'bn',
            $request->query('lang') === 'en' => 'en',
            default => config('app.locale', 'en'),
        };

        if (! in_array($locale, ['en', 'bn'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);
        Session::put('locale', $locale);

        return $next($request);
    }
}
