<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\Brand;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Demo mode shows neutral wording on top of the production text (lang/demo).
        if (Brand::isDemo() && $this->app['translation.loader'] instanceof FileLoader) {
            $this->app['translation.loader']->addPath(Brand::languageOverridePath());
        }

        // Short names in the media table instead of class names.
        Relation::morphMap(['branch' => Branch::class, 'menu_item' => MenuItem::class]);

        // super-admin (Datatech support) always passes authorization checks.
        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->hasRole('super-admin') ? true : null;
        });

        // SMS_DEMO would leak verification codes to browsers, so it must never run in production.
        if (config('services.sms.demo') === true && $this->app->isProduction()) {
            Log::critical('SMS_DEMO is enabled in production and has been ignored. Remove it from the environment.');
        }

        // Server-side render only the public, indexable pages. The back office, POS, checkout and account stay client-side.
        Inertia::withoutSsr(['admin', 'admin/*', 'rider', 'rider/*', 'login', 'checkout', 'account', 'customer/*', 'track/*', 'pay/*']);

        // OpenAPI is the contract for the mobile apps, including on a deployed server.
        Gate::define('viewApiDocs', fn (?User $user = null): bool => ! $this->app->isProduction() || config('app.is_demo') === true);

        // OpenAPI docs are served at /api-docs (see docs/api.md).
        Scramble::configure()->expose(ui: 'api-docs', document: 'api-docs.json');
    }
}
