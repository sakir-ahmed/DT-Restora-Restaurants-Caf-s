<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\LogWastageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WastageRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Wastage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WastageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('inventory.view');

        $branch = $this->currentBranch($request);

        $wastages = Wastage::query()
            ->where('branch_id', $branch->id)
            ->with(['ingredient:id,name', 'unit:id,symbol', 'user:id,name'])
            ->latest()
            ->limit(200)
            ->get();

        return Inertia::render('admin/wastage/Index', [
            'wastages' => $wastages,
            'ingredients' => Ingredient::query()->active()->with('unit:id,symbol')->orderBy('name')->get(['id', 'name', 'unit_id']),
            'units' => Unit::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'reasons' => Wastage::REASONS,
            'canLog' => $request->user('staff')?->can('wastage.log'),
        ]);
    }

    public function store(WastageRequest $request, LogWastageAction $action): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        $action->execute(
            branch: $branch,
            ingredient: Ingredient::query()->findOrFail((int) $request->validated('ingredient_id')),
            unit: Unit::query()->findOrFail((int) $request->validated('unit_id')),
            qty: (float) $request->validated('qty'),
            reasonCode: (string) $request->validated('reason_code'),
            note: $request->validated('note'),
            user: $request->user('staff'),
        );

        return back()->with('success', __('inventory.wastage_logged'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
