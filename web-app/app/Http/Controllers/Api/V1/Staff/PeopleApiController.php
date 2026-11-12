<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Attendance\RecordStaffAttendanceAction;
use App\Actions\Attendance\RegisterAttendanceDeviceAction;
use App\Actions\Payroll\CalculateStaffPayAction;
use App\Actions\Payroll\DeleteSalaryAdvanceAction;
use App\Actions\Payroll\RecordSalaryAdvanceAction;
use App\Enums\PayBasis;
use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\AttendanceDevice;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\SalaryAdvance;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\LetterheadPdf;
use App\Support\DeleteReasons;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Customers, staff, attendance and payroll for the mobile app, matching the web panel's rules.
 */
class PeopleApiController extends Controller
{
    use InteractsWithApi;

    private const ROLES = ['super-admin', 'owner', 'branch-manager', 'cashier', 'waiter', 'kitchen-manager', 'accountant', 'rider'];

    public function customer(Request $request, Customer $customer): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'customers.view');

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->when($user->branch_id !== null, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->latest()
            ->limit(30)
            ->get(['id', 'invoice_no', 'status', 'order_type', 'total', 'created_at']);

        return $this->success([
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'is_active' => (bool) $customer->is_active,
            'points' => (int) $customer->loyaltyEntries()->sum('points'),
            'orders_count' => Order::query()->where('customer_id', $customer->id)->count(),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'status' => $order->status->value,
                'order_type' => $order->order_type->value,
                'total' => (string) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function destroyCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'coupons.manage');

        $coupon->update(['is_active' => false]);

        return $this->success(null, __('coupons.deactivated'));
    }

    public function staff(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'staff.view');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success([
            'employees' => Employee::query()
                ->where('branch_id', $branchModel->id)
                ->with('user:id,name,email,username')
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee): array => $this->employeePayload($employee))
                ->all(),
            'users' => User::query()
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchModel->id))
                ->withWhereHas('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (User $account): array => $this->userPayload($account))
                ->all(),
            'roles' => array_values(array_diff(self::ROLES, ['super-admin'])),
            'pay_bases' => array_column(PayBasis::cases(), 'value'),
            'can_manage' => $user->can('employees.manage'),
            'can_manage_users' => $user->can('staff.manage'),
        ]);
    }

    public function updateEmployee(Request $request, string $branch, Employee $employee): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'employees.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($employee->branch_id === $branchModel->id, 404);

        $validated = $this->validatedEmployee($request, $branchModel->id, $employee);

        $employee->update([
            ...collect($validated)->except(['pay_basis', 'pay_rate', 'monthly_salary', 'device_user_id'])->all(),
            'device_user_id' => $validated['device_user_id'] ?? null,
            ...Employee::payAttributes($validated),
        ]);

        return $this->success($this->employeePayload($employee->fresh()), __('staff.employee_updated'));
    }

    public function deactivateEmployee(Request $request, string $branch, Employee $employee): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'employees.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($employee->branch_id === $branchModel->id, 404);

        $employee->update(['is_active' => false]);

        return $this->success(null, __('staff.employee_deactivated'));
    }

    public function employeePhoto(Request $request, string $branch, Employee $employee): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'employees.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($employee->branch_id === $branchModel->id, 404);

        $validated = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $path = $employee->photo_path;

        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($path !== null && str_starts_with($path, 'staff/')) {
                Storage::disk('public')->delete($path);
            }

            $path = $request->file('photo')?->store('staff', 'public');
        }

        $employee->update(['photo_path' => $path]);

        return $this->success($this->employeePayload($employee->fresh()), __('staff.employee_updated'));
    }

    public function storeUser(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'staff.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $this->validatedUser($request, null);

        $account = User::query()->create([
            ...collect($validated)->except('roles')->all(),
            'password' => Hash::make((string) $validated['password']),
            'branch_id' => $this->resolvedBranchId($request, $user, $branchModel->id),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $account->syncRoles($validated['roles']);

        activity()->performedOn($account)->causedBy($user)->log('staff.user_created');

        return $this->success($this->userPayload($account), __('staff.user_created'), 201);
    }

    public function updateUser(Request $request, string $branch, User $account): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'staff.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $this->validatedUser($request, $account);

        if ($account->id === $user->id && ! in_array('super-admin', $validated['roles'], true) && $account->hasRole('super-admin')) {
            throw ValidationException::withMessages(['roles' => __('staff.cannot_remove_own_super_admin')]);
        }

        $attributes = collect($validated)->except('roles')->all();

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        } else {
            $attributes['password'] = Hash::make((string) $attributes['password']);
        }

        $account->update([...$attributes, 'branch_id' => $this->resolvedBranchId($request, $user, $branchModel->id, $account)]);
        $account->syncRoles($validated['roles']);

        activity()->performedOn($account)->causedBy($user)->log('staff.user_updated');

        return $this->success($this->userPayload($account->fresh()), __('staff.user_updated'));
    }

    public function deactivateUser(Request $request, string $branch, User $account): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'staff.manage');
        $this->branchOrFail($request, $branch);

        if ($account->id === $user->id) {
            throw ValidationException::withMessages(['user' => __('staff.cannot_delete_self')]);
        }

        $account->update(['is_active' => false]);

        activity()->performedOn($account)->causedBy($user)->log('staff.user_deactivated');

        return $this->success(null, __('staff.user_deactivated'));
    }

    public function attendance(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'attendance.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $day = $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString();

        $attendances = StaffAttendance::query()
            ->where('branch_id', $branchModel->id)
            ->whereDate('work_date', $day)
            ->get()
            ->keyBy('employee_id');

        return $this->success([
            'date' => $day,
            'rows' => Employee::query()
                ->where('branch_id', $branchModel->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'designation', 'device_user_id'])
                ->map(function (Employee $employee) use ($attendances): array {
                    $attendance = $attendances->get($employee->id);

                    return [
                        'employee_id' => $employee->id,
                        'employee' => $employee->name,
                        'designation' => $employee->designation,
                        'status' => $attendance?->status ?? 'not_recorded',
                        'clock_in' => $attendance?->clock_in?->toIso8601String(),
                        'clock_out' => $attendance?->clock_out?->toIso8601String(),
                        'hours' => number_format($attendance?->hours() ?? 0.0, 2, '.', ''),
                        'source' => $attendance?->source,
                    ];
                })->values()->all(),
            'devices' => AttendanceDevice::query()
                ->where('branch_id', $branchModel->id)
                ->orderBy('name')
                ->get(['id', 'name', 'serial', 'ip_address', 'is_active', 'last_seen_at'])
                ->all(),
        ]);
    }

    public function recordAttendance(Request $request, string $branch, RecordStaffAttendanceAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'attendance.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'status' => ['required', Rule::in([StaffAttendance::STATUS_PRESENT, StaffAttendance::STATUS_ABSENT])],
            'clock_in' => ['exclude_if:status,absent', 'required_if:status,present', 'nullable', 'date'],
            'clock_out' => ['exclude_if:status,absent', 'nullable', 'date', 'after:clock_in'],
        ]);

        $employee = Employee::query()->where('branch_id', $branchModel->id)->findOrFail($data['employee_id']);

        $action->execute($branchModel, $employee, $data, $user);

        return $this->success(null, __('attendance.recorded'), 201);
    }

    public function storeDevice(Request $request, string $branch, RegisterAttendanceDeviceAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'attendance.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $result = $action->execute($branchModel, $this->validatedDevice($request));

        return $this->success(['id' => $result['device']->id, 'name' => $result['device']->name, 'token' => $result['token']], __('attendance.device_created'), 201);
    }

    public function updateDevice(Request $request, string $branch, AttendanceDevice $device): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'attendance.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($device->branch_id === $branchModel->id, 404);

        $data = $this->validatedDevice($request);

        $device->update([
            'name' => $data['name'],
            'serial' => $data['serial'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->success(null, __('attendance.device_updated'));
    }

    public function rotateDeviceToken(Request $request, string $branch, AttendanceDevice $device, RegisterAttendanceDeviceAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'attendance.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($device->branch_id === $branchModel->id, 404);

        $result = $action->rotate($device);

        return $this->success(['id' => $device->id, 'name' => $device->name, 'token' => $result['token']], __('attendance.token_rotated'));
    }

    public function advances(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'payroll.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $from = $request->date('from') ?? CarbonImmutable::now()->startOfMonth();
        $to = $request->date('to') ?? CarbonImmutable::today();

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'advances' => SalaryAdvance::query()
                ->visible()
                ->where('branch_id', $branchModel->id)
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
                ])->all(),
        ]);
    }

    public function storeAdvance(Request $request, string $branch, RecordSalaryAdvanceAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'payroll.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'advanced_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->where('branch_id', $branchModel->id)->findOrFail($data['employee_id']);

        $action->execute($branchModel, $employee, $data, $user);

        return $this->success(null, __('payroll.advance_recorded'), 201);
    }

    public function deleteAdvance(Request $request, string $branch, SalaryAdvance $advance, DeleteSalaryAdvanceAction $action): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'payroll.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($advance->branch_id === $branchModel->id, 404);

        $reason = DeleteReasons::validate($request);
        $action->execute($advance, $user, $reason['reason'], $reason['note']);

        return $this->success(null, __('payroll.advance_deleted'));
    }

    public function salarySlip(Request $request, string $branch, Employee $employee, CalculateStaffPayAction $calculator, LetterheadPdf $pdf): Response
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'payroll.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($employee->branch_id === $branchModel->id, 404);

        $from = $request->date('from') ?? CarbonImmutable::now()->startOfMonth();
        $to = $request->date('to') ?? CarbonImmutable::today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return $pdf->stream($branchModel, [
            'slip' => [
                'employee' => $employee->displayName(app()->getLocale()),
                'designation' => $employee->designation,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...$calculator->forPeriod($employee, $from, $to),
            ],
        ], 'salary-slip-'.$employee->id.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEmployee(Request $request, int $branchId, ?Employee $employee): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'device_user_id' => ['nullable', 'string', 'max:64', Rule::unique('employees', 'device_user_id')->where('branch_id', $branchId)->ignore($employee?->id)],
            'pay_basis' => ['nullable', Rule::in(array_column(PayBasis::cases(), 'value'))],
            'pay_rate' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['required_without:pay_rate', 'nullable', 'numeric', 'min:0'],
            'joined_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUser(Request $request, ?User $account): array
    {
        $id = $account?->id ?? 'NULL';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$id],
            'username' => ['nullable', 'string', 'max:60', 'unique:users,username,'.$id],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile,'.$id],
            'password' => [$account === null ? 'required' : 'nullable', 'string', 'min:8'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(self::ROLES)],
            'is_active' => ['boolean'],
        ]);

        if (($data['mobile'] ?? null) !== null) {
            $data['mobile'] = normalize_bd_mobile((string) $data['mobile']);
        }

        return $data;
    }

    private function resolvedBranchId(Request $request, User $acting, int $currentBranchId, ?User $existing = null): ?int
    {
        if ($acting->branch_id !== null) {
            return $acting->branch_id;
        }

        $roles = (array) $request->input('roles', []);
        $branchId = $request->integer('branch_id', 0);

        if (array_intersect($roles, ['owner', 'super-admin', 'accountant']) !== [] && $branchId === 0) {
            return null;
        }

        return $branchId > 0 ? $branchId : ($existing?->branch_id ?? $currentBranchId);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDevice(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:64', 'required_without:ip_address'],
            'ip_address' => ['nullable', 'ip', 'required_without:serial'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'name_bn' => $employee->name_bn,
            'designation' => $employee->designation,
            'mobile' => $employee->mobile,
            'device_user_id' => $employee->device_user_id,
            'pay_basis' => $employee->pay_basis instanceof PayBasis ? $employee->pay_basis->value : $employee->pay_basis,
            'pay_rate' => (string) $employee->pay_rate,
            'monthly_salary' => (string) $employee->monthly_salary,
            'joined_at' => $employee->joined_at?->toDateString(),
            'is_active' => (bool) $employee->is_active,
            'photo_url' => $employee->photo_url,
            'user_id' => $employee->user_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'username' => $account->username,
            'mobile' => $account->mobile,
            'branch_id' => $account->branch_id,
            'is_active' => (bool) $account->is_active,
            'roles' => $account->getRoleNames()->all(),
        ];
    }
}
