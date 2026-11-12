<?php

use App\Http\Controllers\Api\V1\Accounting\AccountingApiController;
use App\Http\Controllers\Api\V1\Attendance\DevicePunchController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Customer\CustomerApiController;
use App\Http\Controllers\Api\V1\Payments\PaymentApiController;
use App\Http\Controllers\Api\V1\Rider\RiderApiController;
use App\Http\Controllers\Api\V1\Staff\BackOfficeApiController;
use App\Http\Controllers\Api\V1\Staff\ContentApiController;
use App\Http\Controllers\Api\V1\Staff\InventoryApiController;
use App\Http\Controllers\Api\V1\Staff\MenuApiController;
use App\Http\Controllers\Api\V1\Staff\PeopleApiController;
use App\Http\Controllers\Api\V1\Staff\SalesApiController;
use App\Http\Controllers\Api\V1\Staff\SettingsApiController;
use App\Http\Controllers\Api\V1\Staff\SetupApiController;
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
        Route::post('/otp/send', [OtpController::class, 'send'])->name('otp.send')->middleware('throttle:10,1');
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify')->middleware('throttle:20,1');

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
        Route::get('/settings', [SettingsApiController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsApiController::class, 'update'])->name('settings.update');
        Route::post('/settings/payment-methods', [SettingsApiController::class, 'storeMethod'])->name('settings.methods.store');
        Route::put('/settings/payment-methods/{method}', [SettingsApiController::class, 'updateMethod'])->name('settings.methods.update');
        Route::delete('/settings/payment-methods/{method}', [SettingsApiController::class, 'destroyMethod'])->name('settings.methods.destroy');
        Route::put('/settings/sms', [SettingsApiController::class, 'updateSms'])->name('settings.sms');
        Route::put('/settings/sms/features', [SettingsApiController::class, 'updateSmsFeatures'])->name('settings.sms.features');
        Route::put('/settings/sms/templates/{template}', [SettingsApiController::class, 'updateSmsTemplate'])->name('settings.sms.templates');
        Route::post('/settings/sms/test', [SettingsApiController::class, 'testSms'])->middleware('throttle:5,1,sms-test')->name('settings.sms.test');
        Route::get('/sms/history', [SettingsApiController::class, 'smsHistory'])->name('sms.history');
        Route::post('/sms/promotion', [SettingsApiController::class, 'sendPromotion'])->middleware('throttle:3,1,sms-promo')->name('sms.promotion');
        Route::delete('/ingredients/{ingredient}', [InventoryApiController::class, 'deactivateIngredient'])->name('ingredients.deactivate');
        Route::get('/ingredient-categories', [InventoryApiController::class, 'categories'])->name('ingredient-categories.index');
        Route::post('/ingredient-categories', [InventoryApiController::class, 'storeCategory'])->name('ingredient-categories.store');
        Route::put('/ingredient-categories/{category}', [InventoryApiController::class, 'updateCategory'])->name('ingredient-categories.update');
        Route::delete('/ingredient-categories/{category}', [InventoryApiController::class, 'destroyCategory'])->name('ingredient-categories.destroy');
        Route::post('/stock-transfers/{transfer}/delete', [InventoryApiController::class, 'deleteTransfer'])->name('transfers.delete');
        Route::get('/customers/{customer}', [PeopleApiController::class, 'customer'])->name('customers.show');
        Route::delete('/coupons/{coupon}', [PeopleApiController::class, 'destroyCoupon'])->name('coupons.destroy');
        Route::get('/units', [SetupApiController::class, 'units'])->name('units.index');
        Route::post('/units', [SetupApiController::class, 'storeUnit'])->name('units.store');
        Route::put('/units/{unit}', [SetupApiController::class, 'updateUnit'])->name('units.update');
        Route::delete('/units/{unit}', [SetupApiController::class, 'destroyUnit'])->name('units.destroy');
        Route::get('/reports/{type}', [StaffApiController::class, 'report'])->name('reports');
        Route::get('/customers', [StaffApiController::class, 'customers'])->name('customers');
        Route::post('/orders/{order}/loyalty/redeem', [StaffApiController::class, 'redeemLoyalty'])->name('loyalty.redeem');
        Route::post('/loyalty/adjust', [StaffApiController::class, 'adjustLoyalty'])->name('loyalty.adjust');
        Route::get('/expenses', [BackOfficeApiController::class, 'expenses'])->name('expenses.index');
        Route::post('/expenses', [BackOfficeApiController::class, 'storeExpense'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [BackOfficeApiController::class, 'destroyExpense'])->name('expenses.destroy');
        Route::get('/suppliers', [BackOfficeApiController::class, 'suppliers'])->name('suppliers.index');
        Route::post('/suppliers', [BackOfficeApiController::class, 'storeSupplier'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [BackOfficeApiController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [BackOfficeApiController::class, 'destroySupplier'])->name('suppliers.destroy');
        Route::get('/loyalty', [BackOfficeApiController::class, 'loyalty'])->name('loyalty.index');
        Route::put('/loyalty/settings', [BackOfficeApiController::class, 'updateLoyaltySettings'])->name('loyalty.settings');
        Route::get('/activity-log', [BackOfficeApiController::class, 'activityLog'])->name('activity.index');
        Route::get('/blog-posts', [ContentApiController::class, 'blogPosts'])->name('blog.index');
        Route::post('/blog-posts', [ContentApiController::class, 'storeBlogPost'])->name('blog.store');
        Route::put('/blog-posts/{post}', [ContentApiController::class, 'updateBlogPost'])->name('blog.update');
        Route::delete('/blog-posts/{post}', [ContentApiController::class, 'destroyBlogPost'])->name('blog.destroy');
        Route::get('/careers', [ContentApiController::class, 'careers'])->name('careers.index');
        Route::post('/careers/openings', [ContentApiController::class, 'storeOpening'])->name('careers.openings.store');
        Route::put('/careers/openings/{opening}', [ContentApiController::class, 'updateOpening'])->name('careers.openings.update');
        Route::delete('/careers/openings/{opening}', [ContentApiController::class, 'destroyOpening'])->name('careers.openings.destroy');
        Route::put('/careers/applications/{application}', [ContentApiController::class, 'updateApplication'])->name('careers.applications.update');
        Route::delete('/careers/applications/{application}', [ContentApiController::class, 'destroyApplication'])->name('careers.applications.destroy');
        Route::get('/letters', [ContentApiController::class, 'letters'])->name('letters.index');
        Route::post('/letters', [ContentApiController::class, 'storeLetter'])->name('letters.store');
        Route::put('/letters/{letter}', [ContentApiController::class, 'updateLetter'])->name('letters.update');
        Route::delete('/letters/{letter}', [ContentApiController::class, 'destroyLetter'])->name('letters.destroy');
        Route::get('/letters/{letter}/pdf', [ContentApiController::class, 'letterPdf'])->name('letters.pdf');
        Route::get('/media', [ContentApiController::class, 'media'])->name('media.index');
        Route::post('/media/images', [ContentApiController::class, 'storeImages'])->name('media.images');
        Route::post('/media/{media}/replace', [ContentApiController::class, 'replaceMedia'])->name('media.replace');
        Route::post('/media/{media}/feature', [ContentApiController::class, 'featureMedia'])->name('media.feature');
        Route::post('/media/videos', [ContentApiController::class, 'storeVideo'])->name('media.videos');
        Route::put('/media/{media}', [ContentApiController::class, 'updateMedia'])->name('media.update');
        Route::post('/media/{media}/move', [ContentApiController::class, 'moveMedia'])->name('media.move');
        Route::delete('/media/{media}', [ContentApiController::class, 'destroyMedia'])->name('media.destroy');
        Route::get('/branches', [ContentApiController::class, 'branches'])->name('branches.index');
        Route::post('/branches', [ContentApiController::class, 'storeBranch'])->name('branches.store');
        Route::put('/branches/{branch}', [ContentApiController::class, 'updateBranch'])->name('branches.update');
        Route::delete('/branches/{branch}', [ContentApiController::class, 'destroyBranch'])->name('branches.destroy');
        Route::get('/coupons', [StaffApiController::class, 'coupons'])->name('coupons.index');
        Route::post('/coupons', [StaffApiController::class, 'storeCoupon'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [StaffApiController::class, 'updateCoupon'])->name('coupons.update');
        Route::post('/coupons/validate', [StaffApiController::class, 'validateCoupon'])->name('coupons.validate');
        Route::post('/ingredients', [StaffApiController::class, 'storeIngredient'])->name('ingredients.store');
        Route::put('/ingredients/{ingredient}', [StaffApiController::class, 'updateIngredient'])->name('ingredients.update');
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
            Route::get('/menu-items', [StaffApiController::class, 'menuItemsForManagement'])->name('menu.items');
            Route::post('/menu-items', [StaffApiController::class, 'storeMenuItem'])->name('menu.items.store');
            Route::put('/menu-items/{item}', [StaffApiController::class, 'updateMenuItem'])->name('menu.items.update');
            Route::get('/wastage', [StaffApiController::class, 'wastageIndex'])->name('wastage.index');
            Route::get('/settings', [SettingsApiController::class, 'branchSettings'])->name('settings.branch');
            Route::put('/settings/branch-pos', [SettingsApiController::class, 'updateBranchPos'])->name('settings.branch-pos');
            Route::put('/settings/invoice-print', [SettingsApiController::class, 'updateInvoicePrint'])->name('settings.invoice-print');
            Route::put('/settings/delivery-enabled', [SettingsApiController::class, 'toggleDelivery'])->name('settings.delivery-enabled');
            Route::post('/settings/delivery-zones', [SettingsApiController::class, 'storeZone'])->name('settings.zones.store');
            Route::put('/settings/delivery-zones/{zone}', [SettingsApiController::class, 'updateZone'])->name('settings.zones.update');
            Route::delete('/settings/delivery-zones/{zone}', [SettingsApiController::class, 'destroyZone'])->name('settings.zones.destroy');
            Route::put('/purchases/{purchase}', [InventoryApiController::class, 'updatePurchase'])->name('purchases.update');
            Route::post('/purchases/{purchase}/delete', [InventoryApiController::class, 'deletePurchase'])->name('purchases.delete');
            Route::post('/purchases/{purchase}/receive', [InventoryApiController::class, 'receivePurchase'])->name('purchases.receive');
            Route::post('/wastage/{wastage}/delete', [InventoryApiController::class, 'deleteWastage'])->name('wastage.delete');
            Route::get('/stock-counts', [InventoryApiController::class, 'stockCounts'])->name('stock-counts.index');
            Route::post('/stock-counts', [InventoryApiController::class, 'startStockCount'])->name('stock-counts.store');
            Route::put('/stock-counts/{count}/items', [InventoryApiController::class, 'updateStockCount'])->name('stock-counts.update');
            Route::post('/stock-counts/{count}/close', [InventoryApiController::class, 'closeStockCount'])->name('stock-counts.close');
            Route::post('/stock-counts/{count}/delete', [InventoryApiController::class, 'deleteStockCount'])->name('stock-counts.delete');
            Route::get('/stock-transfers', [InventoryApiController::class, 'transfers'])->name('stock-transfers.index');
            Route::get('/menu-categories', [MenuApiController::class, 'categories'])->name('menu.categories.index');
            Route::post('/menu-categories', [MenuApiController::class, 'storeCategory'])->name('menu.categories.store');
            Route::put('/menu-categories/{category}', [MenuApiController::class, 'updateCategory'])->name('menu.categories.update');
            Route::delete('/menu-categories/{category}', [MenuApiController::class, 'destroyCategory'])->name('menu.categories.destroy');
            Route::delete('/menu-items/{item}', [MenuApiController::class, 'destroyItem'])->name('menu.items.destroy');
            Route::get('/staff', [PeopleApiController::class, 'staff'])->name('staff.index');
            Route::put('/employees/{employee}', [PeopleApiController::class, 'updateEmployee'])->name('employees.update');
            Route::delete('/employees/{employee}', [PeopleApiController::class, 'deactivateEmployee'])->name('employees.deactivate');
            Route::post('/employees/{employee}/photo', [PeopleApiController::class, 'employeePhoto'])->name('employees.photo');
            Route::post('/users', [PeopleApiController::class, 'storeUser'])->name('users.store');
            Route::put('/users/{account}', [PeopleApiController::class, 'updateUser'])->name('users.update');
            Route::delete('/users/{account}', [PeopleApiController::class, 'deactivateUser'])->name('users.deactivate');
            Route::get('/attendance', [PeopleApiController::class, 'attendance'])->name('attendance.index');
            Route::post('/attendance', [PeopleApiController::class, 'recordAttendance'])->name('attendance.record');
            Route::post('/attendance/devices', [PeopleApiController::class, 'storeDevice'])->name('attendance.devices.store');
            Route::put('/attendance/devices/{device}', [PeopleApiController::class, 'updateDevice'])->name('attendance.devices.update');
            Route::post('/attendance/devices/{device}/token', [PeopleApiController::class, 'rotateDeviceToken'])->name('attendance.devices.token');
            Route::get('/payroll/advances', [PeopleApiController::class, 'advances'])->name('payroll.advances.index');
            Route::post('/payroll/advances', [PeopleApiController::class, 'storeAdvance'])->name('payroll.advances.store');
            Route::post('/payroll/advances/{advance}/delete', [PeopleApiController::class, 'deleteAdvance'])->name('payroll.advances.delete');
            Route::get('/payroll/slips/{employee}', [PeopleApiController::class, 'salarySlip'])->name('payroll.slip');
            Route::get('/tables', [SetupApiController::class, 'tables'])->name('tables.index');
            Route::post('/tables', [SetupApiController::class, 'storeTable'])->name('tables.store');
            Route::put('/tables/{table}', [SetupApiController::class, 'updateTable'])->name('tables.update');
            Route::delete('/tables/{table}', [SetupApiController::class, 'destroyTable'])->name('tables.destroy');
            Route::post('/tables/{table}/qr', [SetupApiController::class, 'regenerateTableQr'])->name('tables.qr');
            Route::get('/dues', [SalesApiController::class, 'dues'])->name('dues.index');
            Route::post('/dues/{order}/collect', [SalesApiController::class, 'collectDue'])->name('dues.collect');
            Route::post('/orders/{order}/due', [SalesApiController::class, 'dueSale'])->name('orders.due');
            Route::post('/orders/{order}/delete', [SalesApiController::class, 'deleteOrder'])->name('orders.delete');
            Route::get('/preorders', [SalesApiController::class, 'preorders'])->name('preorders.index');
            Route::post('/preorders', [SalesApiController::class, 'storePreorder'])->name('preorders.store');
            Route::post('/preorders/{preorder}/{action}', [SalesApiController::class, 'transitionPreorder'])->where('action', 'complete|cancel')->name('preorders.transition');
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
            Route::get('/reference', [StaffApiController::class, 'reference'])->name('reference');
            Route::post('/shifts/open', [StaffApiController::class, 'openShift'])->name('shifts.open');
            Route::get('/deliveries', [StaffApiController::class, 'deliveries'])->name('deliveries');
            Route::get('/reservations', [StaffApiController::class, 'reservationsIndex'])->name('reservations.index');
            Route::post('/reservations', [StaffApiController::class, 'storeReservation'])->name('reservations.store');
            Route::patch('/reservations/{reservation}/status', [StaffApiController::class, 'reservationStatus'])->name('reservations.status');
            Route::get('/attendance/today', [StaffApiController::class, 'attendanceToday'])->name('attendance.today');
            Route::post('/attendance/punch', [StaffApiController::class, 'punchAttendance'])->name('attendance.punch');
            Route::get('/payroll', [StaffApiController::class, 'payroll'])->name('payroll');
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
