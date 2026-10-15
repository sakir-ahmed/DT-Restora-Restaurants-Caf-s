<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Metrics\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MetricsService $metrics): Response
    {
        $this->authorize('dashboard.view');

        [$from, $to, $period] = $this->period($request);

        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $user = $request->user('staff');
        $branchId = $this->branchId($request, $user);
        $seesAll = $branchId === null;

        $summary = $metrics->summary($branchId, $from, $to);
        $previous = $metrics->summary($branchId, $prevFrom, $prevTo);

        return Inertia::render('admin/Dashboard', [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $summary,
            'previous' => $previous,
            'salesByDay' => $metrics->salesByDay($branchId, $from, $to),
            'salesByChannel' => $metrics->salesByChannel($branchId, $from, $to),
            'topItems' => $metrics->topItems($branchId, $from, $to),
            'branchComparison' => $seesAll ? $metrics->branchComparison($from, $to) : [],
            'alerts' => [
                'lowStock' => $metrics->lowStock($branchId ?? $this->firstBranchId()),
                'wastageSpike' => $metrics->wastageSpike($branchId ?? $this->firstBranchId()),
            ],
            'setup' => [
                'branches' => Branch::count(),
                'menuItems' => MenuItem::count(),
                'ingredients' => Ingredient::count(),
                'recipes' => Recipe::count(),
                'users' => User::count(),
            ],
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function period(Request $request): array
    {
        $today = CarbonImmutable::today();

        return match ($request->string('period')->toString() ?: 'today') {
            'yesterday' => [$today->subDay()->startOfDay(), $today->subDay()->endOfDay(), 'yesterday'],
            '7days' => [$today->subDays(6)->startOfDay(), $today->endOfDay(), '7days'],
            'month' => [$today->startOfMonth(), $today->endOfDay(), 'month'],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth(), 'last_month'],
            'custom' => [
                CarbonImmutable::parse((string) $request->input('from', $today->toDateString()))->startOfDay(),
                CarbonImmutable::parse((string) $request->input('to', $today->toDateString()))->endOfDay(),
                'custom',
            ],
            default => [$today->startOfDay(), $today->endOfDay(), 'today'],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $length = $from->diffInDays($to) + 1;

        return [$from->subDays($length)->startOfDay(), $from->subDay()->endOfDay()];
    }

    private function branchId(Request $request, $user): ?int
    {
        if ($user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        $branchParam = (int) $request->input('branch', 0);

        if ($branchParam === -1) {
            return null;
        }

        if ($branchParam > 0) {
            return $branchParam;
        }

        $sessionBranch = (int) session('branch_id', 0);

        return $sessionBranch > 0 ? $sessionBranch : null;
    }

    private function firstBranchId(): ?int
    {
        $id = Branch::query()->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
