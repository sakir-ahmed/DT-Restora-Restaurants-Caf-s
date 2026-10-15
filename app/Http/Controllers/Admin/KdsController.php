<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KdsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('kds.access');

        $branch = $this->currentBranch($request);

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Preparing->value, OrderStatus::Ready->value])
            ->with(['items:id,order_id,name,name_bn,quantity,add_ons', 'table:id,name', 'waiter:id,name'])
            ->oldest()
            ->get();

        return Inertia::render('admin/kds/Index', [
            'orders' => $orders,
            'branchId' => $branch->id,
        ]);
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
