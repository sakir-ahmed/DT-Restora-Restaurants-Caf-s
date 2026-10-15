<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('customers.view');

        $search = trim((string) $request->input('search', ''));

        $customers = Customer::query()
            ->withSum('loyaltyEntries as points', 'points')
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'email' => $customer->email,
                'points' => (int) ($customer->points ?? 0),
                'orders_count' => (int) $customer->orders_count,
                'is_active' => $customer->is_active,
            ]);

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }
}
