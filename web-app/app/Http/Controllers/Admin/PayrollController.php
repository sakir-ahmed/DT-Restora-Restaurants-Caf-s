<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payroll\CalculateStaffPayAction;
use App\Actions\Payroll\DeleteSalaryAdvanceAction;
use App\Actions\Payroll\RecordSalaryAdvanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSalaryAdvanceRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Services\LetterheadPdf;
use App\Support\DeleteReasons;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request, CalculateStaffPayAction $calculator): Response
    {
        $this->authorize('payroll.manage');

        $branch = $this->currentBranch($request);
        $from = $request->date('from') ?? CarbonImmutable::now()->startOfMonth();
        $to = $request->date('to') ?? CarbonImmutable::today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get();

        $rows = $employees->map(function (Employee $employee) use ($calculator, $from, $to): array {
            $pay = $calculator->forPeriod($employee, $from, $to);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'designation' => $employee->designation,
                'is_active' => $employee->is_active,
                ...$pay,
            ];
        })->all();

        return Inertia::render('admin/payroll/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'advances' => SalaryAdvance::query()
                ->visible()
                ->where('branch_id', $branch->id)
                ->whereDate('advanced_on', '>=', $from->toDateString())
                ->whereDate('advanced_on', '<=', $to->toDateString())
                ->with('employee:id,name')
                ->orderByDesc('advanced_on')
                ->orderByDesc('id')
                ->get()
                ->map(fn (SalaryAdvance $advance): array => [
                    'id' => $advance->id,
                    'employee' => $advance->employee?->name,
                    'amount' => (string) $advance->amount,
                    'advanced_on' => $advance->advanced_on->toDateString(),
                    'note' => $advance->note,
                    'deleted' => $advance->trashed(),
                ])
                ->all(),
            'employees' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'is_active' => $employee->is_active,
            ])->all(),
        ]);
    }

    public function storeAdvance(StoreSalaryAdvanceRequest $request, RecordSalaryAdvanceAction $action): RedirectResponse
    {
        $branch = $this->currentBranch($request);
        $employee = Employee::query()
            ->where('branch_id', $branch->id)
            ->find($request->integer('employee_id'));

        if ($employee === null) {
            abort(404);
        }

        $action->execute($branch, $employee, $request->validated(), $request->user('staff'));

        return back()->with('success', __('payroll.advance_recorded'));
    }

    public function destroyAdvance(Request $request, SalaryAdvance $advance, DeleteSalaryAdvanceAction $action): RedirectResponse
    {
        $this->authorize('payroll.manage');

        $user = $request->user('staff');
        abort_unless($user->canAccessBranch($advance->branch_id), 404);

        $reason = DeleteReasons::validate($request);

        $action->execute($advance, $user, $reason['reason'], $reason['note']);

        return back()->with('success', __('payroll.advance_deleted'));
    }

    public function slip(Request $request, Employee $employee, CalculateStaffPayAction $calculator, LetterheadPdf $pdf): HttpResponse
    {
        $this->authorize('payroll.manage');

        $branch = $this->currentBranch($request);
        abort_unless($employee->branch_id === $branch->id, 404);

        $from = $request->date('from') ?? CarbonImmutable::now()->startOfMonth();
        $to = $request->date('to') ?? CarbonImmutable::today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return $pdf->stream($branch, [
            'slip' => [
                'employee' => $employee->displayName(app()->getLocale()),
                'designation' => $employee->designation,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...$calculator->forPeriod($employee, $from, $to),
            ],
        ], 'salary-slip-'.$employee->id.'.pdf');
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
