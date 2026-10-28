<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DemoJourney;
use App\Services\DemoSmsInbox;
use App\Services\Metrics\MetricsService;
use App\Services\PosSettings;
use App\Services\PublicCache;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Translation groups the public website needs. The back office loads all of them.
     *
     * @var list<string>
     */
    private const PUBLIC_TRANSLATION_GROUPS = [
        'common', 'web', 'site', 'auth', 'coupons', 'orders', 'payment_method',
        'payments', 'status', 'menu', 'loyalty', 'errors', 'staff', 'nav', 'legal', 'receipt', 'reasons', 'sync', 'demo', 'tablemenu',
    ];

    public function share(Request $request): array
    {
        $user = $request->user('staff');
        $customer = $request->user('customer');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'isDemo' => config('app.is_demo') === true,
            'demoSession' => fn () => config('app.is_demo') === true ? app(DemoJourney::class)->shared($request) : null,
            'locale' => app()->getLocale(),
            'translations' => flat_translations(app()->getLocale(), $request->routeIs('web.*') ? self::PUBLIC_TRANSLATION_GROUPS : null),
            'auth' => [
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                    'email' => $customer->email,
                ] : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'mobile' => $user->mobile,
                    'locale' => $user->locale,
                    'branch_id' => $user->branch_id,
                    'roles' => $user->getRoleNames()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->all(),
                ] : null,
            ],
            'branches' => $user
                ? $user->accessibleBranches()
                    ->map(fn (Branch $branch) => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'name_bn' => $branch->name_bn,
                        'code' => $branch->code,
                    ])
                    ->all()
                : [],
            'smsDemo' => SmsService::isDemo(),
            'demoSms' => fn () => app(DemoSmsInbox::class)->drain($request),
            'site' => fn () => $request->routeIs('web.*') ? PublicCache::remember('site', fn (): array => $this->siteData()) : null,
            'currentBranchId' => $user ? $this->currentBranchId($user) : null,
            'sounds' => $user ? fn (): array => PosSettings::soundsFor(Branch::query()->find($this->currentBranchId($user))) : null,
            'notifications' => $user ? $this->cachedNotifications($user) : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'order' => $request->session()->get('order'),
                'payment' => $request->session()->get('payment'),
                'placed' => $request->session()->get('placed_order'),
                'device_token' => $request->session()->get('device_token'),
                'device_name' => $request->session()->get('device_name'),
            ],
        ];
    }

    private function currentBranchId(User $user): ?int
    {
        if ($user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        $selected = session('branch_id');

        if ($selected !== null && $user->canAccessBranch((int) $selected)) {
            return (int) $selected;
        }

        $first = Branch::query()->orderBy('id')->value('id');

        return $first !== null ? (int) $first : null;
    }

    /**
     * Stock and wastage alerts only change as orders are rung up, so a minute-old answer is fine
     * and saves two aggregate queries on every admin navigation.
     *
     * @return array<int, array{id: string, icon: string, title: string, body: string, href: string}>
     */
    private function cachedNotifications(User $user): array
    {
        $branchId = $this->currentBranchId($user);

        return Cache::remember('admin-alerts:'.($branchId ?? 'all').':'.app()->getLocale(), 60, fn (): array => $this->notifications($user));
    }

    /**
     * @return array<int, array{id: string, icon: string, title: string, body: string, href: string}>
     */
    private function notifications(User $user): array
    {
        $metrics = app(MetricsService::class);
        $branchId = $this->currentBranchId($user);
        $items = [];

        $lowStock = $metrics->lowStock($branchId);

        if ($lowStock !== []) {
            $items[] = [
                'id' => 'low-stock',
                'icon' => 'boxes-stacked',
                'title' => __('dashboard.alerts.low_stock_count', ['count' => count($lowStock)]),
                'body' => $lowStock[0]['name'],
                'href' => '/admin/ingredients',
            ];
        }

        $spike = $metrics->wastageSpike($branchId);

        if ($spike !== null) {
            $items[] = [
                'id' => 'wastage-spike',
                'icon' => 'trash',
                'title' => __('dashboard.alerts.wastage_spike'),
                'body' => $spike['today'],
                'href' => '/admin/wastage',
            ];
        }

        return $items;
    }

    /**
     * Branch contact details shown in the public header, footer and menus.
     *
     * @return array<string, mixed>
     */
    private function siteData(): array
    {
        return [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'name' => $branch->name,
                    'name_bn' => $branch->name_bn,
                    'code' => $branch->code,
                    'slug' => $branch->publicSlug(),
                    'address' => $branch->address,
                    'address_bn' => $branch->address_bn,
                    'city' => $branch->city,
                    'city_bn' => $branch->city_bn,
                    'phone' => $branch->phone,
                ])
                ->values()
                ->all(),
            'social' => Setting::current()->only(['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_whatsapp']),
        ];
    }
}
