<?php

use App\Jobs\SendSmsJob;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\LoyaltyService;
use App\Services\Metrics\MetricsService;
use App\Services\SmsCampaignService;
use Illuminate\Support\Facades\Schedule;

// Loyalty points expiry (FIFO, idempotent).
Schedule::call(fn () => app(LoyaltyService::class)->expireDue())->dailyAt('02:00');

// Owner daily sales summary by SMS.
Schedule::call(function (): void {
    $metrics = app(MetricsService::class);
    $summary = $metrics->summary(null, now()->subDay()->startOfDay(), now()->subDay()->endOfDay());

    foreach (User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner']))->pluck('mobile') as $mobile) {
        if ($mobile !== null) {
            SendSmsJob::deliver((string) $mobile, __('web.owner_daily_summary', [
                'date' => now()->subDay()->toDateString(),
                'count' => $summary['orders'],
                'total' => $summary['gross_sales'],
            ]));
        }
    }
})->dailyAt('21:00');

// Daily salary allocation so the ledger matches the owner dashboard.
Schedule::call(fn () => app(AccountingService::class)->postDailySalary(now()))
    ->dailyAt('23:50')
    ->name('salary-allocation')
    ->withoutOverlapping();

Schedule::call(function (): void {
    $campaigns = app(SmsCampaignService::class);
    $campaigns->sendBirthdayOffers();
    $campaigns->sendWinBack();
})->dailyAt('10:00')->name('sms-campaigns')->withoutOverlapping();

// Daily database backup; clean old backups weekly.
Schedule::command('backup:clean')->weekly()->sundays()->at('01:00');
Schedule::command('backup:run --only-db')->daily()->at('01:30');

// Demo sites start over each morning so visitor changes do not stick.
Schedule::command('demo:reset')
    ->dailyAt('04:00')
    ->timezone('Asia/Dhaka')
    ->when(fn (): bool => config('app.is_demo') === true)
    ->withoutOverlapping(120)
    ->runInBackground();
