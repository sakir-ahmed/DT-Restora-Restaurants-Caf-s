<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payroll\CalculateStaffPayAction;
use App\Actions\Payroll\RecordSalaryAdvanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSalaryAdvanceRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\LetterheadPdf;
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
