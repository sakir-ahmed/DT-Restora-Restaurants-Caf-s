<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,bn'],
        ]);

        session(['locale' => $validated['locale']]);

        $user = $request->user('staff');

        if ($user !== null) {
            $user->forceFill(['locale' => $validated['locale']])->save();
        }

        return back();
    }
}
