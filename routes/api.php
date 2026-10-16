<?php

use App\Http\Controllers\Api\V1\Accounting\AccountingApiController;
use App\Http\Controllers\Api\V1\Attendance\DevicePunchController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Customer\CustomerApiController;
use App\Http\Controllers\Api\V1\Payments\PaymentApiController;
use App\Http\Controllers\Api\V1\Rider\RiderApiController;
use App\Http\Controllers\Api\V1\Staff\StaffApiController;
use App\Http\Controllers\Api\V1\Sync\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'data' => ['status' => 'ok'],
    ]))->name('health');

    Route::post('/attendance/punches', [DevicePunchController::class, 'store'])
        ->middleware(['attendance.device', 'throttle:120,1'])
        ->name('attendance.punches');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/google', [AuthController::class, 'google'])->name('google');
        Route::post('/otp/send', [OtpController::class, 'send'])->name('otp.send');
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::prefix('sync')->name('sync.')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/changes', [SyncController::class, 'changes'])->name('changes');
        Route::post('/push', [SyncController::class, 'push'])->name('push');
    });

    Route::prefix('rider')->name('rider.')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/deliveries', [RiderApiController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{delivery}/status', [RiderApiController::class, 'updateStatus'])->name('status');
        Route::post('/location', [RiderApiController::class, 'location'])->name('location');
        Route::post('/deliveries/{delivery}/verify-otp', [RiderApiController::class, 'verifyOtp'])->name('verify-otp');
        Route::get('/cod-summary', [RiderApiController::class, 'codSummary'])->name('cod-summary');
    });

    Route::get('/track/{token}', [CustomerApiController::class, 'guestTrack'])->name('track');

    Route::prefix('customer')->name('customer.')->group(function (): void {
        Route::get('/branches', [CustomerApiController::class, 'branches'])->name('branches');
        Route::get('/branches/{branch}/menu', [CustomerApiController::class, 'menu'])->name('menu');
        Route::post('/delivery/quote', [CustomerApiController::class, 'quote'])->name('quote');

        Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
            Route::get('/me', [CustomerApiController::class, 'me'])->name('me');
            Route::patch('/me', [CustomerApiController::class, 'updateMe'])->name('me.update');
            Route::get('/addresses', [CustomerApiController::class, 'addresses'])->name('addresses.index');
            Route::post('/addresses', [CustomerApiController::class, 'storeAddress'])->name('addresses.store');
            Route::put('/addresses/{address}', [CustomerApiController::class, 'updateAddress'])->name('addresses.update');
            Route::delete('/addresses/{address}', [CustomerApiController::class, 'destroyAddress'])->name('addresses.destroy');
            Route::get('/orders', [CustomerApiController::class, 'orders'])->name('orders.index');
            Route::post('/orders', [CustomerApiController::class, 'storeOrder'])->name('orders.store');
            Route::get('/orders/{order}', [CustomerApiController::class, 'showOrder'])->name('orders.show');
            Route::post('/orders/{order}/cancel', [CustomerApiController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/pay', [CustomerApiController::class, 'pay'])->name('orders.pay');
            Route::get('/orders/{order}/tracking', [CustomerApiController::class, 'tracking'])->name('orders.tracking');
            Route::get('/loyalty', [CustomerApiController::class, 'loyalty'])->name('loyalty');
            Route::post('/coupons/validate', [CustomerApiController::class, 'validateCoupon'])->name('coupons.validate');
            Route::post('/devices', [CustomerApiController::class, 'storeDevice'])->name('devices.store');
            Route::delete('/devices/{token}', [CustomerApiController::class, 'destroyDevice'])->where('token', '.*')->name('devices.destroy');
        });
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
        Route::get('/dashboard', [StaffApiController::class, 'dashboard'])->name('dashboard');
        Route::get('/reports/{type}', [StaffApiController::class, 'report'])->name('reports');
        Route::get('/customers', [StaffApiController::class, 'customers'])->name('customers');
        Route::post('/orders/{order}/loyalty/redeem', [StaffApiController::class, 'redeemLoyalty'])->name('loyalty.redeem');
        Route::post('/loyalty/adjust', [StaffApiController::class, 'adjustLoyalty'])->name('loyalty.adjust');
        Route::get('/coupons', [StaffApiController::class, 'coupons'])->name('coupons.index');
        Route::post('/coupons', [StaffApiController::class, 'storeCoupon'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [StaffApiController::class, 'updateCoupon'])->name('coupons.update');
        Route::post('/coupons/validate', [StaffApiController::class, 'validateCoupon'])->name('coupons.validate');
        Route::get('/recipes', [StaffApiController::class, 'recipes'])->name('recipes.index');
        Route::post('/recipes', [StaffApiController::class, 'storeRecipe'])->name('recipes.store');
        Route::put('/recipes/{recipe}', [StaffApiController::class, 'updateRecipe'])->name('recipes.update');
        Route::get('/recipes/{recipe}/cost', [StaffApiController::class, 'recipeCost'])->name('recipes.cost');
        Route::post('/stock-transfers', [StaffApiController::class, 'storeTransfer'])->name('transfers.store');
        Route::post('/stock-transfers/{transfer}/approve', [StaffApiController::class, 'approveTransfer'])->name('transfers.approve');
        Route::post('/stock-transfers/{transfer}/receive', [StaffApiController::class, 'receiveTransfer'])->name('transfers.receive');
        Route::post('/payments/bkash/create', [PaymentApiController::class, 'bkash'])->name('payments.bkash');
        Route::post('/payments/sslcommerz/init', [PaymentApiController::class, 'sslcommerz'])->name('payments.sslcommerz');
        Route::post('/shifts/{shift}/close', [StaffApiController::class, 'closeShift'])->name('shifts.close');
        Route::post('/deliveries/{delivery}/assign', [StaffApiController::class, 'assignDelivery'])->name('deliveries.assign');
        Route::post('/void-requests/{voidRequest}/approve', [StaffApiController::class, 'approveVoid'])->name('voids.approve');
        Route::get('/aggregators/channels', [StaffApiController::class, 'aggregatorChannels'])->name('aggregators.index');
        Route::put('/aggregators/channels/{channel}', [StaffApiController::class, 'updateAggregatorChannel'])->name('aggregators.update');
        Route::post('/aggregators/orders/{order}/accept', [StaffApiController::class, 'aggregatorDecision'])->name('aggregators.accept');
        Route::post('/aggregators/orders/{order}/reject', [StaffApiController::class, 'aggregatorDecision'])->name('aggregators.reject');
        Route::post('/aggregators/menu-sync', [StaffApiController::class, 'syncAggregatorMenu'])->name('aggregators.menu-sync');

        Route::prefix('branches/{branch}')->name('branches.')->group(function (): void {
            Route::get('/menu', [StaffApiController::class, 'menu'])->name('menu');
            Route::get('/orders', [StaffApiController::class, 'orders'])->name('orders.index');
            Route::post('/orders', [StaffApiController::class, 'storeOrder'])->name('orders.store');
            Route::get('/orders/{order}', [StaffApiController::class, 'showOrder'])->name('orders.show');
            Route::patch('/orders/{order}/status', [StaffApiController::class, 'updateStatus'])->name('orders.status');
            Route::post('/orders/{order}/payments', [StaffApiController::class, 'pay'])->name('orders.pay');
            Route::post('/orders/{order}/void-requests', [StaffApiController::class, 'requestVoid'])->name('orders.void');
            Route::get('/inventory/stock', [StaffApiController::class, 'stock'])->name('stock');
            Route::post('/inventory/wastage', [StaffApiController::class, 'wastage'])->name('wastage');
            Route::get('/purchases', [StaffApiController::class, 'purchases'])->name('purchases.index');
            Route::post('/purchases', [StaffApiController::class, 'storePurchase'])->name('purchases.store');
            Route::get('/employees', [StaffApiController::class, 'employees'])->name('employees.index');
            Route::post('/employees', [StaffApiController::class, 'storeEmployee'])->name('employees.store');
            Route::post('/shifts/open', [StaffApiController::class, 'openShift'])->name('shifts.open');
            Route::get('/deliveries', [StaffApiController::class, 'deliveries'])->name('deliveries');
            Route::post('/orders/manual-aggregator', [StaffApiController::class, 'manualAggregator'])->name('aggregators.manual');
        });

        Route::prefix('accounting')->name('accounting.')->group(function (): void {
            Route::get('/accounts', [AccountingApiController::class, 'accounts'])->name('accounts.index');
            Route::post('/accounts', [AccountingApiController::class, 'storeAccount'])->name('accounts.store');
            Route::put('/accounts/{account}', [AccountingApiController::class, 'updateAccount'])->name('accounts.update');
            Route::get('/journals', [AccountingApiController::class, 'journals'])->name('journals.index');
            Route::post('/journals', [AccountingApiController::class, 'storeJournal'])->name('journals.store');
            Route::post('/journals/{journal}/approve', [AccountingApiController::class, 'approveJournal'])->name('journals.approve');
            Route::post('/journals/{journal}/reverse', [AccountingApiController::class, 'reverseJournal'])->name('journals.reverse');
            Route::get('/vouchers', [AccountingApiController::class, 'vouchers'])->name('vouchers.index');
            Route::post('/vouchers', [AccountingApiController::class, 'storeVoucher'])->name('vouchers.store');
            Route::get('/payables', [AccountingApiController::class, 'payables'])->name('payables');
            Route::post('/periods/{period}/lock', [AccountingApiController::class, 'lockPeriod'])->name('periods.lock');
            Route::get('/reports/{statement}', [AccountingApiController::class, 'statement'])->name('reports');
        });
    });
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum')
    ->name('api.user');
