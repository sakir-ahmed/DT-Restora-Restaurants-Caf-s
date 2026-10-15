<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

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

        // Server-side render only the public, indexable pages. The back office, POS, checkout and account stay client-side.
        Inertia::withoutSsr(['admin', 'admin/*', 'rider', 'rider/*', 'login', 'checkout', 'account', 'customer/*', 'track/*', 'pay/*']);

        // OpenAPI is the contract for the mobile apps, including on a deployed server.
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);

        // OpenAPI docs are served at /api-docs (see docs/api.md).
        Scramble::configure()->expose(ui: 'api-docs', document: 'api-docs.json');
    }
}
