<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('staff.view');

        $branch = $this->currentBranch($request);

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->with('user:id,name,email,username')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->where(function ($query) use ($branch): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->withWhereHas('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'mobile' => $user->mobile,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->all(),
            ]);

        return Inertia::render('admin/staff/Index', [
            'employees' => $employees,
            'users' => $users,
            'roles' => ['owner', 'branch-manager', 'cashier', 'waiter', 'kitchen-manager', 'accountant', 'rider'],
            'canManage' => $request->user('staff')?->can('employees.manage'),
            'canManageUsers' => $request->user('staff')?->can('staff.manage'),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        $validated = $request->validated();

        Employee::query()->create([
            ...collect($validated)->except(['pay_basis', 'pay_rate', 'monthly_salary', 'device_user_id'])->all(),
            ...Employee::payAttributes($validated),
            'branch_id' => $branch->id,
        ]);

        return back()->with('success', __('staff.employee_created'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();

        $employee->update([
            ...collect($validated)->except(['pay_basis', 'pay_rate', 'monthly_salary', 'device_user_id'])->all(),
            ...Employee::payAttributes($validated),
        ]);

        return back()->with('success', __('staff.employee_updated'));
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('employees.manage');

        $employee->update(['is_active' => false]);

        return back()->with('success', __('staff.employee_deactivated'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branchId = $user->branch_id ?? (int) session('branch_id', 0);

        $branch = Branch::query()->find($branchId);

        if ($branch === null) {
            $branch = Branch::query()->orderBy('id')->firstOrFail();
        }

        return $branch;
    }
}
