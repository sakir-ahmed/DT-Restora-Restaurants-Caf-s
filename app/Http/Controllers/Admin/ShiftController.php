<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\CloseShiftAction;
use App\Actions\Sales\OpenShiftAction;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function open(Request $request, OpenShiftAction $action): RedirectResponse
    {
        $this->authorize('shifts.manage');

        $branch = $this->currentBranch($request);

        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'terminal' => ['nullable', 'string', 'max:10'],
        ]);

        $action->execute(
            branch: $branch,
            cashier: $request->user('staff'),
            openingCash: (float) $validated['opening_cash'],
            terminalCode: (string) ($validated['terminal'] ?? 'T01'),
        );

        return back()->with('success', __('orders.shift_opened'));
    }

    public function close(Request $request, Shift $shift, CloseShiftAction $action): RedirectResponse
    {
        $this->authorize('shifts.manage');

        $user = $request->user('staff');

        abort_unless($user->canAccessBranch($shift->branch_id), 404);
        abort_unless($user->id === $shift->user_id || $user->can('orders.void'), 403);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $closed = $action->execute($shift, (float) $validated['counted_cash']);

        return back()->with('success', __('orders.shift_closed', [
            'expected' => $closed->expected_cash,
            'difference' => $closed->difference,
        ]));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
