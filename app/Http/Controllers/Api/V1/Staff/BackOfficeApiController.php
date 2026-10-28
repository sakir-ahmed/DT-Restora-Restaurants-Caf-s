<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltySetting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\LoyaltyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Back-office modules that the web admin offers and the mobile app mirrors:
 * expenses, suppliers, loyalty programme and the activity log.
 */
class BackOfficeApiController extends Controller
{
    use InteractsWithApi;

    public function expenses(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'expenses.manage');
        $branch = $this->workingBranch($request, $user);

        $expenses = Expense::query()
            ->where('branch_id', $branch->id)
            ->with('user:id,name')
            ->latest('expense_date')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Expense $expense): array => $this->expensePayload($expense))
            ->all();

        return $this->success([
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function storeExpense(Request $request, AccountingService $accounting): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'expenses.manage');
        $branch = $this->workingBranch($request, $user);

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', Expense::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $expense = Expense::query()->create([
            ...$validated,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);

        $accounting->postExpense($expense, $user);

        return $this->success($this->expensePayload($expense->load('user:id,name')), null, 201);
    }

    public function destroyExpense(Request $request, Expense $expense): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'expenses.manage');

        abort_unless($user->canAccessBranch($expense->branch_id), 404);

        $expense->delete();

        return $this->success(['deleted' => true]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.view');

        return $this->success(Supplier::query()->orderBy('name')->get());
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $supplier = Supplier::query()->create($this->validatedSupplier($request));

        return $this->success($supplier, null, 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        $supplier->update($this->validatedSupplier($request));

        return $this->success($supplier->refresh());
    }

    public function destroySupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'inventory.manage');

        if ($supplier->purchases()->exists()) {
            return $this->error('SUPPLIER_IN_USE', __('inventory.supplier_delete_not_empty'), 409);
        }

        $supplier->delete();

        return $this->success(['deleted' => true]);
    }

    public function loyalty(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'loyalty.manage');

        $lookup = null;
        $mobile = trim((string) $request->query('mobile', ''));

        if ($mobile !== '') {
            $customer = Customer::query()->where('mobile', normalize_bd_mobile($mobile))->first();

            $lookup = $customer ? [
                'customer' => $customer->only(['id', 'name', 'mobile']),
                'balance' => $loyalty->balance($customer),
                'entries' => LoyaltyEntry::query()
                    ->where('customer_id', $customer->id)
                    ->with('order:id,invoice_no')
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn (LoyaltyEntry $entry): array => [
                        'id' => $entry->id,
                        'type' => $entry->type,
                        'points' => $entry->points,
                        'balance_after' => $entry->balance_after,
                        'note' => $entry->note,
                        'invoice_no' => $entry->order?->invoice_no,
                        'created_at' => $entry->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ] : null;
        }

        return $this->success([
            'settings' => LoyaltySetting::current()->only(['earn_rate', 'point_value', 'expiry_days', 'is_enabled']),
            'lookup' => $lookup,
            'totals' => [
                'customers_with_points' => Customer::query()->whereHas('loyaltyEntries')->count(),
                'points_earned' => (int) LoyaltyEntry::query()->where('points', '>', 0)->where('type', 'earned')->sum('points'),
                'points_redeemed' => abs((int) LoyaltyEntry::query()->where('type', 'redeemed')->sum('points')),
                'points_expired' => abs((int) LoyaltyEntry::query()->where('type', 'expired')->sum('points')),
            ],
        ]);
    }

    public function updateLoyaltySettings(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'loyalty.manage');

        $validated = $request->validate([
            'earn_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'point_value' => ['required', 'numeric', 'min:0'],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $settings = LoyaltySetting::current();
        $settings->update($validated);

        return $this->success($settings->only(['earn_rate', 'point_value', 'expiry_days', 'is_enabled']));
    }

    public function activityLog(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'activity.view');

        $entries = Activity::query()
            ->with('causer')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'description' => $activity->description,
                'causer' => $this->causerName($activity->causer),
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();

        return $this->success($entries);
    }

    /**
     * The branch the request works on: an explicit `branch_id` the user may
     * access, otherwise the user's own branch, otherwise the first branch.
     */
    private function workingBranch(Request $request, User $user): Branch
    {
        $requested = (int) ($request->input('branch_id') ?: $user->branch_id ?: 0);

        if ($requested > 0) {
            $branch = Branch::query()->find($requested);

            if ($branch !== null && $user->canAccessBranch($branch->id)) {
                return $branch;
            }

            abort(404);
        }

        return Branch::query()->orderBy('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSupplier(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'description' => $expense->description,
            'amount' => (string) $expense->amount,
            'user' => $expense->user?->name,
        ];
    }

    private function causerName(?Model $causer): ?string
    {
        return $causer instanceof User || $causer instanceof Customer ? $causer->name : null;
    }
}
