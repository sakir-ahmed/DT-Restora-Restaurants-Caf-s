<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('expenses.manage');

        $branch = $this->currentBranch($request);

        $expenses = Expense::query()
            ->where('branch_id', $branch->id)
            ->with('user:id,name')
            ->latest('expense_date')
            ->limit(200)
            ->get();

        return Inertia::render('admin/expenses/Index', [
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
            'canManage' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('expenses.manage');

        $branch = $this->currentBranch($request);

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', Expense::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $expense = Expense::query()->create([
            ...$validated,
            'branch_id' => $branch->id,
            'user_id' => $request->user('staff')?->id,
        ]);

        app(AccountingService::class)->postExpense($expense, $request->user('staff'));

        return back()->with('success', __('expenses.recorded'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('expenses.manage');

        abort_unless($request->user('staff')->canAccessBranch($expense->branch_id), 404);

        $expense->delete();

        return back()->with('success', __('expenses.deleted'));
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
