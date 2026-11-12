<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\DataVersion;
use App\Models\RestaurantTable;
use App\Models\Unit;
use App\Support\StandardUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Units of measure and the floor plan for the mobile app. The rules match the web Settings and
 * Tables pages (fixed standard units, a unit in use keeps its size, a table with orders is
 * deactivated instead of deleted).
 */
class SetupApiController extends Controller
{
    use InteractsWithApi;

    public function units(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        return $this->success(Unit::query()
            ->orderBy('type')
            ->orderBy('factor')
            ->orderBy('id')
            ->get()
            ->map(fn (Unit $unit): array => $this->unitPayload($unit))
            ->all());
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $unit = Unit::query()->create($this->validatedUnit($request, null) + ['is_system' => false]);

        activity()->causedBy($user)->performedOn($unit)->log('unit.created');

        return $this->success($this->unitPayload($unit), __('units.saved'), 201);
    }

    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        abort_if($unit->is_system, 403, __('units.fixed_note'));

        $unit->update($this->validatedUnit($request, $unit));

        activity()->causedBy($user)->performedOn($unit)->log('unit.updated');

        return $this->success($this->unitPayload($unit->fresh()), __('units.saved'));
    }

    public function destroyUnit(Request $request, Unit $unit): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        abort_if($unit->is_system, 403, __('units.fixed_note'));

        if ($unit->isInUse()) {
            throw ValidationException::withMessages(['unit' => __('units.in_use', ['name' => $unit->name])]);
        }

        activity()->causedBy($user)->performedOn($unit)->log('unit.deleted');
        $unit->delete();

        return $this->success(null, __('units.deleted'));
    }

    public function tables(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'tables.view');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success([
            'tables' => RestaurantTable::query()
                ->where('branch_id', $branchModel->id)
                ->get()
                ->sortBy(fn (RestaurantTable $table): string => mb_strtolower(($table->area ?? '')."\0".$table->name), SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (RestaurantTable $table): array => $this->tablePayload($table))
                ->all(),
            'can_manage' => $user->can('tables.manage'),
        ]);
    }

    public function storeTable(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'tables.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate($this->tableRules($branchModel->id, null));

        $table = RestaurantTable::query()->create([
            'branch_id' => $branchModel->id,
            'name' => trim($validated['name']),
            'area' => filled($validated['area'] ?? null) ? $validated['area'] : null,
            'seats' => $validated['seats'],
            'status' => 'free',
            'is_active' => $request->boolean('is_active', true),
        ]);

        DataVersion::bump();
        activity()->performedOn($table)->causedBy($user)->log('tables.created');

        return $this->success($this->tablePayload($table), __('tables.created'), 201);
    }

    public function updateTable(Request $request, string $branch, RestaurantTable $table): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'tables.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($table->branch_id === $branchModel->id, 404);

        $validated = $request->validate($this->tableRules($branchModel->id, $table->id));

        $table->update([
            'name' => trim($validated['name']),
            'area' => filled($validated['area'] ?? null) ? $validated['area'] : null,
            'seats' => $validated['seats'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $table->is_active,
        ]);

        DataVersion::bump();
        activity()->performedOn($table)->causedBy($user)->log('tables.updated');

        return $this->success($this->tablePayload($table->fresh()), __('tables.updated'));
    }

    public function destroyTable(Request $request, string $branch, RestaurantTable $table): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'tables.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($table->branch_id === $branchModel->id, 404);

        if ($table->orders()->exists()) {
            $table->update(['is_active' => false]);
            DataVersion::bump();
            activity()->performedOn($table)->causedBy($user)->log('tables.deactivated');

            return $this->success($this->tablePayload($table->fresh()), __('tables.deactivated'));
        }

        activity()->performedOn($table)->causedBy($user)->log('tables.deleted');
        $table->delete();
        DataVersion::bump();

        return $this->success(null, __('tables.deleted'));
    }

    public function regenerateTableQr(Request $request, string $branch, RestaurantTable $table): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'tables.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($table->branch_id === $branchModel->id, 404);

        $table->rotateQrToken();
        activity()->performedOn($table)->causedBy($user)->log('tables.qr_regenerated');

        return $this->success($this->tablePayload($table->fresh()), __('tables.qr_regenerated', ['table' => $table->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRules(int $branchId, ?int $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'not_regex:/[\\/]/', Rule::unique('restaurant_tables', 'name')->where('branch_id', $branchId)->ignore($ignoreId)],
            'area' => ['nullable', 'string', 'max:50'],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePayload(RestaurantTable $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'area' => $table->area,
            'seats' => $table->seats,
            'state' => $table->liveState(),
            'is_active' => (bool) $table->is_active,
            'qr_url' => route('web.table', ['token' => $table->qr_token]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unitPayload(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'name_bn' => $unit->name_bn,
            'symbol' => $unit->symbol,
            'type' => $unit->type,
            'factor' => (string) $unit->factor,
            'is_system' => (bool) $unit->is_system,
            'in_use' => $unit->isInUse(),
        ];
    }

    /**
     * Name, symbol and, for a custom unit, its size: "1 [unit] = [qty] [reference unit]".
     *
     * @return array<string, mixed>
     */
    private function validatedUnit(Request $request, ?Unit $unit): array
    {
        $locked = $unit !== null && $unit->isInUse();

        $rules = [
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'symbol' => ['required', 'string', 'max:20', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} .\/-]*$/u', Rule::unique('units', 'symbol')->ignore($unit?->id)],
        ];

        if (! $locked) {
            $rules += [
                'type' => [$unit === null ? 'required' : 'sometimes', Rule::in(StandardUnits::TYPES)],
                'qty' => ['required', 'numeric', 'gt:0', 'max:1000000'],
                'reference_unit_id' => ['required', 'integer', 'exists:units,id'],
            ];
        }

        $data = $request->validate($rules);

        $attributes = [
            'name' => trim($data['name']),
            'name_bn' => isset($data['name_bn']) && trim($data['name_bn']) !== '' ? trim($data['name_bn']) : null,
            'symbol' => trim($data['symbol']),
        ];

        if ($locked) {
            return $attributes;
        }

        $reference = Unit::query()->findOrFail((int) $data['reference_unit_id']);
        $type = $unit?->type ?? $data['type'];

        if ($reference->type !== $type || $reference->id === $unit?->id) {
            throw ValidationException::withMessages(['reference_unit_id' => __('units.reference_invalid')]);
        }

        $factor = round((float) $data['qty'] * (float) $reference->factor, 6);

        if ($factor < 0.000001 || $factor > 1_000_000_000) {
            throw ValidationException::withMessages(['qty' => __('units.size_out_of_range')]);
        }

        return $attributes + [
            'type' => $type,
            'factor' => $factor,
            'base_unit_id' => Unit::query()->where('symbol', StandardUnits::BASE[$type])->value('id'),
        ];
    }
}
