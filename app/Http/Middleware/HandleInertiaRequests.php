<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user('staff');
        $customer = $request->user('customer');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => app()->getLocale(),
            'translations' => flat_translations(app()->getLocale()),
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
            'currentBranchId' => $user ? $this->currentBranchId($user) : null,
            'notifications' => $user ? $this->notifications($user) : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'order' => $request->session()->get('order'),
                'payment' => $request->session()->get('payment'),
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
}
