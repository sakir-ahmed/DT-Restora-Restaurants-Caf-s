<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // super-admin (Datatech support) always passes authorization checks.
        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->hasRole('super-admin') ? true : null;
        });

        // OpenAPI docs are served at /api-docs (see docs/api.md).
        Scramble::configure()->expose(ui: 'api-docs', document: 'api-docs.json');
    }
}
