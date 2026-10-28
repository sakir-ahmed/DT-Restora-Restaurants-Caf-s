<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The delivery areas a branch serves. Website orders can only be delivered to
 * these areas, so delivery never goes "anywhere".
 */
class DeliveryZoneController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $branch = $this->currentBranch($request);

        $zone = DeliveryZone::query()->create($this->validated($request) + [
            'branch_id' => $branch->id,
            'sort_order' => (int) DeliveryZone::query()->where('branch_id', $branch->id)->max('sort_order') + 1,
        ]);

        activity()->causedBy($request->user('staff'))->performedOn($zone)->log('delivery_zone.created');

        return back()->with('success', __('settings.zone_saved'));
    }

    public function update(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $this->authorize('settings.manage');

        abort_unless($zone->branch_id === $this->currentBranch($request)->id, 404);

        $zone->update($this->validated($request));

        activity()->causedBy($request->user('staff'))->performedOn($zone)->log('delivery_zone.updated');

        return back()->with('success', __('settings.zone_saved'));
    }

    public function destroy(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $this->authorize('settings.manage');

        abort_unless($zone->branch_id === $this->currentBranch($request)->id, 404);

        // Orders and saved addresses keep their history: the link is cleared by the database.
        activity()->causedBy($request->user('staff'))->performedOn($zone)->log('delivery_zone.deleted');

        $zone->delete();

        return back()->with('success', __('settings.zone_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'fee_type' => ['required', Rule::in([DeliveryZone::FEE_FIXED, DeliveryZone::FEE_FLEXIBLE])],
            'min_order' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'eta_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_active' => ['required', 'boolean'],
        ], [], [
            'name' => __('settings.zone_name'),
            'fee' => __('settings.zone_fee'),
            'min_order' => __('settings.zone_min_order'),
            'eta_minutes' => __('settings.zone_eta'),
        ]);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        return Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0)) ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
