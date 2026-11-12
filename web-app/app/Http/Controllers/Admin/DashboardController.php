<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Preorder;
use App\Models\Recipe;
use App\Models\Reservation;
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

        $lowStock = $metrics->lowStock($branchId ?? $this->firstBranchId());

        return Inertia::render('admin/Dashboard', [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'previousFrom' => $prevFrom->toDateString(),
            'previousTo' => $prevTo->toDateString(),
            'summary' => $summary,
            'previous' => $previous,
            'salesByDay' => $metrics->salesByDay($branchId, $from, $to),
            'salesByChannel' => $metrics->salesByChannel($branchId, $from, $to),
            'topItems' => $metrics->topItems($branchId, $from, $to),
            'expensesByCategory' => $metrics->expensesByCategory($branchId, $from, $to),
            'dues' => $metrics->dues($branchId, $from, $to),
            'branchComparison' => $seesAll ? $metrics->branchComparison($from, $to) : [],
            // Menu-book pre-orders still waiting, plus bookings that carry a pre-ordered menu.
            'preorders' => Preorder::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', Preorder::PENDING)
                ->count()
                + Reservation::query()
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->where('status', Reservation::BOOKED)
                    ->whereNotNull('preorder')
                    ->count(),
            'lowStockItems' => array_slice($lowStock, 0, 50),
            'waitingPreorders' => $this->waitingPreorders($branchId),
            'alerts' => [
                'lowStock' => count($lowStock),
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
     * The pre-orders still waiting, soonest first: menu-book pre-orders and bookings that carry a
     * pre-ordered menu. A short list for the dashboard card.
     *
     * @return array<int, array{kind: string, no: string, name: ?string, at: ?string, total: string, items: int}>
     */
    private function waitingPreorders(?int $branchId): array
    {
        $menuBook = Preorder::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', Preorder::PENDING)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Preorder $preorder): array => [
                'kind' => 'menu_book',
                'no' => $preorder->order_no,
                'name' => $preorder->customer_name,
                'at' => ($preorder->expected_at ?? $preorder->created_at)->toIso8601String(),
                'planned' => $preorder->expected_at !== null,
                'total' => (string) $preorder->total,
                'items' => $preorder->itemCount(),
            ]);

        $bookings = Reservation::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', Reservation::BOOKED)
            ->whereNotNull('preorder')
            ->orderBy('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'kind' => 'booking',
                'no' => $reservation->booking_no,
                'name' => $reservation->guest_name,
                'at' => $reservation->starts_at->toIso8601String(),
                'planned' => true,
                'total' => (string) $reservation->preorder_total,
                'items' => (int) collect($reservation->preorder)->sum('quantity'),
            ]);

        return $menuBook->concat($bookings)->sortBy('at')->take(30)->values()->all();
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
        $length = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

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
