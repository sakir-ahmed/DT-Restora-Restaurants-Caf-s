<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\ApproveStockTransferAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockTransferRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\StockTransfer;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('transfers.manage');

        $user = $request->user('staff');

        $transfers = StockTransfer::query()
            ->where(function ($query) use ($user): void {
                $branchId = $user->branch_id ?? (int) session('branch_id', 0);
                $query->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId);
            })
            ->with(['fromBranch:id,name,code', 'toBranch:id,name,code', 'items.ingredient:id,name', 'items.unit:id,symbol'])
            ->latest()
            ->limit(50)
            ->get();

        $branches = Branch::query()->when(
            $user->branch_id !== null,
            fn ($query) => $query->whereKey($user->branch_id),
        )->orderBy('code')->get(['id', 'name', 'code']);

        return Inertia::render('admin/transfers/Index', [
            'transfers' => $transfers,
            'branches' => $branches,
            'ingredients' => Ingredient::query()->active()->with('unit:id,symbol')->orderBy('name')->get(['id', 'name', 'unit_id']),
            'units' => Unit::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'symbol', 'type']),
            'canManage' => $user->can('transfers.manage'),
        ]);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $user = $request->user('staff');

        $fromBranchId = $user->branch_id ?? (int) $request->validated('from_branch_id');

        $toBranchId = (int) $request->validated('to_branch_id');

        if ($toBranchId === $fromBranchId) {
            return back()->with('error', __('inventory.transfer_same_branch'));
        }

        $transfer = DB::transaction(function () use ($request, $user, $fromBranchId, $toBranchId): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => StockTransfer::STATUS_REQUESTED,
                'note' => $request->validated('note'),
                'requested_by' => $user->id,
            ]);

            foreach ($request->validated('items') as $line) {
                $transfer->items()->create([
                    'ingredient_id' => (int) $line['ingredient_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => $line['qty'],
                ]);
            }

            return $transfer;
        });

        return back()->with('success', __('inventory.transfer_requested'));
    }

    public function approve(Request $request, StockTransfer $transfer, ApproveStockTransferAction $action): RedirectResponse
    {
        $this->ensureInvolved($request, $transfer);

        $action->execute($transfer, $request->user('staff'));

        return back()->with('success', __('inventory.transfer_approved'));
    }

    public function receive(Request $request, StockTransfer $transfer, ReceiveStockTransferAction $action): RedirectResponse
    {
        $this->ensureInvolved($request, $transfer);

        $action->execute($transfer, $request->user('staff'));

        return back()->with('success', __('inventory.transfer_received'));
    }

    /**
     * Branch-bound staff may only act on transfers their branch sends or receives.
     */
    private function ensureInvolved(Request $request, StockTransfer $transfer): void
    {
        $branchId = $request->user('staff')->branch_id;

        abort_if(
            $branchId !== null && ! in_array($branchId, [$transfer->from_branch_id, $transfer->to_branch_id], true),
            404,
        );
    }
}
