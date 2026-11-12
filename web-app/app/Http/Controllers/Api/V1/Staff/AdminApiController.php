<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\AggregatorChannel;
use App\Models\BankStatementLine;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Order;
use App\Models\User;
use App\Services\Aggregators\Foodpanda\FoodpandaClient;
use App\Services\Aggregators\PartnerCatalog;
use App\Services\Aggregators\PlatformAdapters;
use App\Services\Aggregators\UberEats\UberEatsClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Account profile, bank statement matching, fiscal-year lock and the remaining aggregator actions
 * (connection test, outlet status, import, payouts) for the mobile app.
 */
class AdminApiController extends Controller
{
    use InteractsWithApi;

    public function profile(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        return $this->success($this->profilePayload($user));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($user->id)],
            'locale' => ['required', Rule::in(['en', 'bn'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $avatarPath = $user->avatar_path;

        if ($request->hasFile('avatar') || $request->boolean('remove_avatar')) {
            if ($avatarPath !== null && str_starts_with($avatarPath, 'avatars/')) {
                Storage::disk('public')->delete($avatarPath);
            }

            $avatarPath = $request->file('avatar')?->store('avatars', 'public');
        }

        if (filled($validated['mobile'] ?? null)) {
            $validated['mobile'] = normalize_bd_mobile((string) $validated['mobile']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'username' => $validated['username'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'locale' => $validated['locale'],
            'avatar_path' => $avatarPath,
        ]);

        return $this->success($this->profilePayload($user->fresh()), __('profile.updated'));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        // The session guard of the web panel is not there on a token request, so the check is done by hand.
        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('validation.current_password')]);
        }

        $user->update(['password' => $validated['password']]);

        activity()->performedOn($user)->causedBy($user)->log('staff.password_changed');

        return $this->success(null, __('profile.password_changed'));
    }

    public function fiscalYears(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'accounting.view');

        return $this->success(FiscalYear::query()->orderByDesc('starts_at')->get()->map(fn (FiscalYear $year): array => [
            'id' => $year->id,
            'name' => $year->name,
            'starts_at' => $year->starts_at?->toDateString(),
            'ends_at' => $year->ends_at?->toDateString(),
            'is_locked' => (bool) $year->is_locked,
            'locked_at' => $year->locked_at?->toIso8601String(),
        ])->all());
    }

    public function lockFiscalYear(Request $request, FiscalYear $year): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'periods.lock');

        $year->update(['is_locked' => true, 'locked_at' => now()]);

        activity()->performedOn($year)->causedBy($user)->log('fiscal_year.locked');

        return $this->success(null, __('accounting.year_locked'));
    }

    public function bankStatements(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'accounting.view');

        return $this->success(BankStatementLine::query()->latest('occurred_on')->limit(100)->get()->map(fn (BankStatementLine $line): array => [
            'id' => $line->id,
            'account_code' => $line->account_code,
            'occurred_on' => $line->occurred_on?->toDateString(),
            'amount' => (string) $line->amount,
            'description' => $line->description,
            'matched' => $line->isMatched(),
            'journal_id' => $line->journal_id,
        ])->all());
    }

    public function storeBankStatement(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'journals.post');

        $validated = $request->validate([
            'account_code' => ['required', Rule::in(['1100', '1110', '1120', '1130'])],
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $line = BankStatementLine::query()->create([...$validated, 'user_id' => $user->id]);

        return $this->success(['id' => $line->id], __('accounting.statement_saved'), 201);
    }

    public function matchBankStatement(Request $request, BankStatementLine $line): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'journals.post');

        if ($line->isMatched()) {
            throw ValidationException::withMessages(['journal_id' => __('accounting.already_matched')]);
        }

        $validated = $request->validate(['journal_id' => ['required', 'integer', 'exists:journals,id']]);

        $journal = Journal::query()->with('lines.account')->findOrFail($validated['journal_id']);

        if (! $journal->isPosted()) {
            throw ValidationException::withMessages(['journal_id' => __('accounting.match_posted')]);
        }

        $movement = round((float) $journal->lines
            ->filter(fn ($journalLine) => $journalLine->account?->code === $line->account_code)
            ->sum(fn ($journalLine) => (float) $journalLine->debit - (float) $journalLine->credit), 2);

        if (abs($movement - (float) $line->amount) > 0.001) {
            throw ValidationException::withMessages(['journal_id' => __('accounting.match_amount')]);
        }

        $line->update(['journal_id' => $journal->id, 'matched_at' => now()]);

        activity()->performedOn($line)->causedBy($user)->log('statement.matched');

        return $this->success(null, __('accounting.statement_matched'));
    }

    public function aggregatorOverview(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        PartnerCatalog::ensureRows();

        return $this->success([
            'channels' => AggregatorChannel::query()->withCount('payouts')->orderBy('name')->get()->map(fn (AggregatorChannel $channel): array => [
                'id' => $channel->id,
                'key' => $channel->key,
                'name' => $channel->name,
                'is_active' => (bool) $channel->is_active,
                'environment' => $channel->environment,
                'last_error' => $channel->last_error,
                'payouts_count' => $channel->payouts_count,
                'has_api' => PartnerCatalog::hasApi($channel->key),
                'can_push' => $channel->isFoodpanda() || $channel->isUberEats(),
            ])->all(),
            'orders' => Order::query()
                ->whereNotNull('aggregator_channel_id')
                ->with('aggregatorChannel:id,key,name')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'invoice_no' => $order->invoice_no,
                    'channel' => $order->aggregatorChannel?->name,
                    'status' => $order->status->value,
                    'total' => (string) $order->total,
                    'commission_amount' => (string) $order->commission_amount,
                    'created_at' => $order->created_at?->toIso8601String(),
                ])->all(),
            'commission_totals' => [
                'today' => number_format((float) Order::query()->whereNotNull('aggregator_channel_id')->whereDate('created_at', today())->sum('commission_amount'), 2, '.', ''),
                'month' => number_format((float) Order::query()->whereNotNull('aggregator_channel_id')->whereMonth('created_at', now()->month)->sum('commission_amount'), 2, '.', ''),
            ],
        ]);
    }

    public function toggleChannel(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        $channel->update(['is_active' => ! $channel->is_active]);

        activity()->performedOn($channel)->causedBy($user)->withProperties(['is_active' => $channel->is_active])->log('aggregator.toggled');

        return $this->success(['is_active' => (bool) $channel->is_active], __($channel->is_active ? 'aggregators.channel_enabled' : 'aggregators.channel_disabled', ['name' => $channel->name]));
    }

    public function payouts(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        return $this->success($channel->payouts()->latest('period_to')->limit(50)->get()->all());
    }

    public function recordPayout(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'received_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $range = [$validated['period_from'], $validated['period_to'].' 23:59:59'];
        $orders = Order::query()->where('aggregator_channel_id', $channel->id)->whereBetween('created_at', $range);
        $netExpected = (float) (clone $orders)->sum('total') - (float) (clone $orders)->sum('commission_amount');

        $payout = $channel->payouts()->create([
            'period_from' => $validated['period_from'],
            'period_to' => $validated['period_to'],
            'expected_amount' => $netExpected,
            'received_amount' => $validated['received_amount'],
            'status' => abs((float) $validated['received_amount'] - $netExpected) < 0.01 ? 'reconciled' : 'pending',
        ]);

        return $this->success($payout, __('aggregators.payout_recorded'), 201);
    }

    public function testConnection(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');
        abort_unless($channel->isFoodpanda() || $channel->isUberEats(), 404);

        try {
            $channel->isUberEats() ? (new UberEatsClient($channel))->token(fresh: true) : (new FoodpandaClient($channel))->token(fresh: true);
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            throw ValidationException::withMessages(['channel' => __('aggregators.push_failed', ['error' => $exception->getMessage()])]);
        }

        $channel->forceFill(['last_error' => null])->save();

        return $this->success(null, __('aggregators.connection_ok', ['environment' => $channel->environment]));
    }

    public function outletStatus(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');
        abort_unless($channel->isFoodpanda() || $channel->isUberEats(), 404);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'status' => ['required', 'in:OPEN,CLOSED_TODAY,CLOSED_UNTIL,CHECKIN'],
            'closed_reason' => ['nullable', 'in:TOO_BUSY_NO_DRIVERS,TOO_BUSY_KITCHEN,UPDATES_IN_MENU,TECHNICAL_PROBLEM,CLOSED,OTHER,BAD_WEATHER,HOLIDAY_SPECIAL_DAY'],
            'closed_until' => ['nullable', 'required_if:status,CLOSED_UNTIL', 'date', 'after:now'],
        ]);

        abort_unless($user->canAccessBranch((int) $validated['branch_id']), 404);

        $vendorId = $channel->vendorIdFor((int) $validated['branch_id']);

        if ($vendorId === null) {
            throw ValidationException::withMessages(['branch_id' => __('aggregators.vendor_map_missing')]);
        }

        try {
            if ($channel->isUberEats()) {
                (new UberEatsClient($channel))->setStoreStatus(
                    $vendorId,
                    $validated['status'] === 'OPEN' ? 'ONLINE' : 'PAUSED',
                    $validated['status'] === 'OPEN' ? null : ($validated['closed_reason'] ?? 'OTHER'),
                );
            } else {
                (new FoodpandaClient($channel))->setVendorStatus(
                    $vendorId,
                    $validated['status'],
                    $validated['closed_reason'] ?? 'OTHER',
                    isset($validated['closed_until']) ? CarbonImmutable::parse($validated['closed_until'])->utc()->format('Y-m-d\TH:i:s\Z') : null,
                );
            }
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            throw ValidationException::withMessages(['status' => __('aggregators.push_failed', ['error' => $exception->getMessage()])]);
        }

        activity()->performedOn($channel)->causedBy($user)->withProperties($validated)->log('aggregator.outlet_status');

        return $this->success(null, __('aggregators.outlet_updated'));
    }

    public function importOrders(Request $request, AggregatorChannel $channel): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'aggregators.manage');
        $adapter = PlatformAdapters::for($channel);
        abort_unless($adapter !== null, 404);

        try {
            $result = $adapter->importRecent();
        } catch (Throwable $exception) {
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();

            throw ValidationException::withMessages(['channel' => __('aggregators.push_failed', ['error' => $exception->getMessage()])]);
        }

        return $this->success($result, __('aggregators.orders_imported', $result));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'mobile' => $user->mobile,
            'locale' => $user->locale ?? app()->getLocale(),
            'avatar_url' => $user->avatar_url,
            'roles' => $user->getRoleNames()->all(),
            'branch' => $user->branch?->name,
        ];
    }
}
