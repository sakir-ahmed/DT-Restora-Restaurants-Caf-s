<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Support\StandardUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Units of measure and how they relate: 1 kg = 1000 g, 1 box = 12 pcs.
 * The standard units are fixed; owners add their own (box, large box ...).
 */
class UnitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $data = $this->validated($request, null);

        $unit = Unit::query()->create($data + ['is_system' => false]);

        activity()->causedBy($request->user('staff'))->performedOn($unit)->log('unit.created');

        return back()->with('success', __('units.saved'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('settings.manage');

        abort_if($unit->is_system, 403, __('units.fixed_note'));

        $data = $this->validated($request, $unit);

        $unit->update($data);

        activity()->causedBy($request->user('staff'))->performedOn($unit)->log('unit.updated');

        return back()->with('success', __('units.saved'));
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('settings.manage');

        abort_if($unit->is_system, 403, __('units.fixed_note'));

        if ($unit->isInUse()) {
            return back()->with('error', __('units.in_use', ['name' => $unit->name]));
        }

        activity()->causedBy($request->user('staff'))->performedOn($unit)->log('unit.deleted');

        $unit->delete();

        return back()->with('success', __('units.deleted'));
    }

    /**
     * Name, symbol and, for a custom unit, how big it is: "1 [unit] = [qty] [reference unit]".
     * The size is stored as a multiple of the type's base unit (g, l, pcs).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Unit $unit): array
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

        $data = $request->validate($rules, [], [
            'name' => __('units.name'),
            'name_bn' => __('units.name_bn'),
            'symbol' => __('units.symbol'),
            'qty' => __('units.quantity'),
            'reference_unit_id' => __('units.reference'),
        ]);

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

        $base = Unit::query()->where('symbol', StandardUnits::BASE[$type])->first();

        return $attributes + [
            'type' => $type,
            'factor' => $factor,
            'base_unit_id' => $base?->id,
        ];
    }
}
