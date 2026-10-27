<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestaurantTableRequest;
use App\Models\Branch;
use App\Models\DataVersion;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantTableController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('tables.view');

        $branch = $this->currentBranch($request);

        $tables = RestaurantTable::query()
            ->where('branch_id', $branch->id)
            ->get()
            ->sortBy(
                fn (RestaurantTable $table): string => mb_strtolower(($table->area ?? '')."\0".$table->name),
                SORT_NATURAL | SORT_FLAG_CASE,
            )
            ->values()
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'area' => $table->area,
                'seats' => $table->seats,
                // Occupied comes from open orders, reserved from a running booking: nobody sets it by hand.
                'state' => $table->liveState(),
                'is_active' => $table->is_active,
                'qr_url' => route('web.table', ['token' => $table->qr_token]),
                'qr_generated_at' => $table->qr_generated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/tables/Index', [
            'tables' => $tables,
            'branchName' => $branch->name,
            'canManage' => (bool) $request->user('staff')?->can('tables.manage'),
        ]);
    }

    /**
     * Every active table with its QR link, laid out to print as table cards.
     */
    public function qrSheet(Request $request): Response
    {
        $this->authorize('tables.view');

        $branch = $this->currentBranch($request);

        $tables = RestaurantTable::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (RestaurantTable $table): string => mb_strtolower(($table->area ?? '')."\0".$table->name), SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'area' => $table->area,
                'seats' => $table->seats,
                'qr_url' => route('web.table', ['token' => $table->qr_token]),
            ]);

        return Inertia::render('admin/tables/QrSheet', [
            'tables' => $tables,
            'branch' => $branch->only(['name', 'name_bn', 'phone']),
        ]);
    }

    /**
     * Issue a new secret for one table. The QR code printed before stops working at once.
     */
    public function regenerateQr(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->authorize('tables.manage');

        $this->ensureAccessible($request, $table);

        $table->rotateQrToken();

        activity()->performedOn($table)->causedBy($request->user('staff'))->log('tables.qr_regenerated');

        return back()->with('success', __('tables.qr_regenerated', ['table' => $table->name]));
    }

    public function regenerateAllQr(Request $request): RedirectResponse
    {
        $this->authorize('tables.manage');

        $branch = $this->currentBranch($request);

        $tables = RestaurantTable::query()->where('branch_id', $branch->id)->get();

        DB::transaction(fn () => $tables->each(fn (RestaurantTable $table) => $table->rotateQrToken()));

        activity()->causedBy($request->user('staff'))->withProperties(['branch_id' => $branch->id, 'tables' => $tables->count()])->log('tables.qr_regenerated_all');

        return back()->with('success', __('tables.qr_regenerated_all', ['count' => $tables->count()]));
    }

    public function store(RestaurantTableRequest $request): RedirectResponse
    {
        $branch = $this->currentBranch($request);
        $validated = $request->validated();

        if ($request->boolean('is_range')) {
            $this->createRange($branch, $validated, $request->user('staff'));

            return back()->with('success', __('tables.range_created'));
        }

        $table = RestaurantTable::query()->create([
            'branch_id' => $branch->id,
            'name' => $validated['name'],
            'area' => $validated['area'] ?? null,
            'seats' => $validated['seats'],
            'status' => 'free',
            'is_active' => $request->boolean('is_active', true),
        ]);

        DataVersion::bump();

        activity()->performedOn($table)->causedBy($request->user('staff'))->log('tables.created');

        return back()->with('success', __('tables.created'));
    }

    public function update(RestaurantTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $this->ensureAccessible($request, $table);

        $validated = $request->validated();

        $table->update([
            'name' => $validated['name'],
            'area' => $validated['area'] ?? null,
            'seats' => $validated['seats'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $table->is_active,
        ]);

        DataVersion::bump();

        activity()->performedOn($table)->causedBy($request->user('staff'))->log('tables.updated');

        return back()->with('success', __('tables.updated'));
    }

    public function destroy(Request $request, RestaurantTable $table): RedirectResponse
    {
        $this->authorize('tables.manage');

        $this->ensureAccessible($request, $table);

        if ($table->orders()->exists()) {
            $table->update(['is_active' => false]);

            DataVersion::bump();

            activity()->performedOn($table)->causedBy($request->user('staff'))->log('tables.deactivated');

            return back()->with('success', __('tables.deactivated'));
        }

        activity()->performedOn($table)->causedBy($request->user('staff'))->log('tables.deleted');

        $table->delete();

        DataVersion::bump();

        return back()->with('success', __('tables.deleted'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createRange(Branch $branch, array $validated, ?User $user): void
    {
        $from = (int) $validated['from'];
        $to = (int) $validated['to'];
        $names = [];

        for ($number = $from; $number <= $to; $number++) {
            $names[] = $validated['prefix'].$number;
        }

        $existing = RestaurantTable::query()
            ->where('branch_id', $branch->id)
            ->whereIn('name', $names)
            ->orderBy('name')
            ->pluck('name');

        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prefix' => __('tables.range_conflict', ['names' => $existing->implode(', ')]),
            ]);
        }

        DB::transaction(function () use ($branch, $validated, $names): void {
            foreach ($names as $name) {
                RestaurantTable::query()->create([
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'area' => $validated['area'] ?? null,
                    'seats' => $validated['seats'],
                    'status' => 'free',
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]);
            }
        });

        DataVersion::bump();

        activity()->causedBy($user)->withProperties([
            'branch_id' => $branch->id,
            'names' => $names,
        ])->log('tables.range_created');
    }

    private function ensureAccessible(Request $request, RestaurantTable $table): void
    {
        abort_unless($request->user('staff')?->canAccessBranch((int) $table->branch_id), 404);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
