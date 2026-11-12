<?php

declare(strict_types=1);

use App\Models\AggregatorChannel;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create(['password' => 'old-password-1']);
    $this->token = $this->owner->createToken('staff', ['staff'])->plainTextToken;
});

it('reads and updates the profile and changes the password only with the current one', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.name', $this->owner->name);

    $this->withToken($this->token)->postJson('/api/v1/profile', ['name' => 'New Name', 'locale' => 'bn'])
        ->assertOk()->assertJsonPath('data.locale', 'bn');

    $this->withToken($this->token)->putJson('/api/v1/profile/password', [
        'current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ])->assertUnprocessable();

    $this->withToken($this->token)->putJson('/api/v1/profile/password', [
        'current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ])->assertOk();
});

it('adds a bank statement line and refuses to match a missing journal', function (): void {
    $id = $this->withToken($this->token)->postJson('/api/v1/accounting/bank-statements', [
        'account_code' => '1110', 'occurred_on' => now()->toDateString(), 'amount' => 500, 'description' => 'Deposit',
    ])->assertCreated()->json('data.id');

    expect($this->withToken($this->token)->getJson('/api/v1/accounting/bank-statements')->json('data'))->toHaveCount(1);

    $this->withToken($this->token)->postJson('/api/v1/accounting/bank-statements/'.$id.'/match', ['journal_id' => 99999])->assertUnprocessable();

    expect(BankStatementLine::query()->find($id)->isMatched())->toBeFalse();
});

it('lists and locks a fiscal year', function (): void {
    $year = FiscalYear::query()->create(['name' => 'FY test', 'starts_at' => now()->startOfYear(), 'ends_at' => now()->endOfYear()]);

    $this->withToken($this->token)->getJson('/api/v1/accounting/fiscal-years')->assertOk()->assertJsonPath('data.0.is_locked', false);
    $this->withToken($this->token)->postJson('/api/v1/accounting/fiscal-years/'.$year->id.'/lock')->assertOk();

    expect($year->fresh()->is_locked)->toBeTrue();
});

it('shows the aggregator overview, toggles a channel and records a payout', function (): void {
    $data = $this->withToken($this->token)->getJson('/api/v1/aggregators/overview')->assertOk()->json('data');
    expect($data)->toHaveKeys(['channels', 'orders', 'commission_totals']);

    $channel = AggregatorChannel::query()->first();
    expect($channel)->not->toBeNull();

    $before = $channel->is_active;
    $this->withToken($this->token)->postJson('/api/v1/aggregators/channels/'.$channel->id.'/toggle')->assertOk();
    expect($channel->fresh()->is_active)->toBe(! $before);

    $this->withToken($this->token)->postJson('/api/v1/aggregators/channels/'.$channel->id.'/payouts', [
        'period_from' => now()->subWeek()->toDateString(), 'period_to' => now()->toDateString(), 'received_amount' => 0,
    ])->assertCreated()->assertJsonPath('data.status', 'reconciled');

    $this->withToken($this->token)->getJson('/api/v1/aggregators/channels/'.$channel->id.'/payouts')->assertOk();
});

it('keeps accounting and aggregator admin to permitted roles', function (): void {
    $waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $token = $waiter->createToken('staff', ['staff'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/aggregators/overview')->assertForbidden();
});

it('tells the app where live updates connect, or that they are off', function (): void {
    $data = $this->withToken($this->token)->getJson('/api/v1/realtime')->assertOk()->json('data');

    expect($data)->toHaveKeys(['enabled', 'auth_endpoint', 'channels'])
        ->and($data['enabled'])->toBeFalse()
        ->and($data['channels'])->toBe([]);
});
