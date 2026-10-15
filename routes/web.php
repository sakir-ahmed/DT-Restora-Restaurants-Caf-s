<?php

use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\AggregatorController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchSwitchController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\KdsController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\LoyaltyRedemptionController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\StockCountController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UiKitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WastageController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Payments\GatewayCallbackController;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Rider\RiderLocationWebController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AggregatorWebhookController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\CustomerAuthController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\TrackController;
use App\Http\Controllers\Web\WebPaymentController;
use App\Models\Branch;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('web.home');
Route::get('/menu/{branchCode?}', MenuController::class)->name('web.menu');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('web.checkout');
Route::post('/checkout', [CheckoutController::class, 'place'])->name('web.checkout.place');
Route::post('/coupons/validate', [CheckoutController::class, 'validateCoupon'])->name('web.coupons.validate')->middleware('throttle:20,1');
Route::get('/track/{token}', [TrackController::class, 'show'])->name('web.track');
Route::get('/pay/{order}/{gateway}', [WebPaymentController::class, 'start'])->name('web.payment.start');

// QR table ordering: scanning the code opens the menu for that table.
Route::get('/q/{table}', fn (string $table) => redirect()->route('web.menu', ['table' => $table]))->name('web.qr');

Route::get('/customer/login', [CustomerAuthController::class, 'showLogin'])->name('web.customer.login');
Route::post('/customer/otp/send', [CustomerAuthController::class, 'sendOtp'])->name('web.customer.otp.send');
Route::post('/customer/otp/verify', [CustomerAuthController::class, 'verifyOtp'])->name('web.customer.otp.verify');
Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('web.customer.logout');

Route::middleware('auth:customer')->group(function (): void {
    Route::get('/account', AccountController::class)->name('web.account');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('web.account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('web.account.addresses.destroy');
});

// Rider web view (staff guard, rider role only, mobile-first).
Route::prefix('rider')->name('rider.')->middleware(['auth'])->group(function (): void {
    Route::get('/', [RiderController::class, 'index'])->name('index');
    Route::post('deliveries/{delivery}/status', [RiderController::class, 'updateStatus'])->name('status');
    Route::post('duty', [RiderController::class, 'toggleDuty'])->name('duty');
    Route::post('location', [RiderLocationWebController::class, 'store'])->name('location');
});

Route::inertia('/privacy', 'web/Privacy')->name('web.privacy');
Route::inertia('/terms', 'web/Terms')->name('web.terms');

Route::get('/sitemap.xml', function () {
    $entries = collect([route('web.home'), route('web.privacy'), route('web.terms')]);

    foreach (Branch::query()->where('is_active', true)->pluck('code') as $code) {
        $entries->push(route('web.menu', ['branchCode' => strtolower($code)]));
    }

    $body = $entries->map(fn (string $url) => '<url><loc>'.$url.'</loc></url>')->join('');

    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>', 200, ['Content-Type' => 'application/xml']);
})->name('web.sitemap');

Route::post('/locale', LocaleController::class)->name('locale.switch');

// Aggregator webhooks: public, one per channel, CSRF-excluded.
Route::post('/webhooks/aggregators/{key}', AggregatorWebhookController::class)->name('aggregators.webhook');

// Gateway callbacks and IPN are public web routes (CSRF-excluded).
Route::get('/payments/bkash/callback', [GatewayCallbackController::class, 'bkashCallback'])->name('payments.bkash.callback');
Route::post('/payments/sslcommerz/ipn', [GatewayCallbackController::class, 'sslcommerzIpn'])->name('payments.sslcommerz.ipn');
Route::get('/payments/sslcommerz/return', [GatewayCallbackController::class, 'sslcommerzReturn'])->name('payments.sslcommerz.return');

Route::middleware('guest')->group(function (): void {
    Route::inertia('/login', 'auth/Login')->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');

    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'permission:admin.access'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('ui-kit', UiKitController::class)->name('ui-kit');

        Route::post('branch-switch', BranchSwitchController::class)->name('branch-switch');

        Route::get('branches', [BranchController::class, 'index'])->name('branches.index')->middleware('permission:branches.view');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store')->middleware('permission:branches.manage');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update')->middleware('permission:branches.manage');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy')->middleware('permission:branches.manage');

        Route::get('menu', [AdminMenuController::class, 'index'])->name('menu.index')->middleware('permission:menu.view');
        Route::post('menu/categories', [AdminMenuController::class, 'storeCategory'])->name('menu.categories.store')->middleware('permission:menu.manage');
        Route::put('menu/categories/{category}', [AdminMenuController::class, 'updateCategory'])->name('menu.categories.update')->middleware('permission:menu.manage');
        Route::delete('menu/categories/{category}', [AdminMenuController::class, 'destroyCategory'])->name('menu.categories.destroy')->middleware('permission:menu.manage');
        Route::post('menu/items', [AdminMenuController::class, 'storeItem'])->name('menu.items.store')->middleware('permission:menu.manage');
        Route::put('menu/items/{item}', [AdminMenuController::class, 'updateItem'])->name('menu.items.update')->middleware('permission:menu.manage');
        Route::delete('menu/items/{item}', [AdminMenuController::class, 'destroyItem'])->name('menu.items.destroy')->middleware('permission:menu.manage');

        Route::get('staff', [EmployeeController::class, 'index'])->name('staff.index')->middleware('permission:staff.view');
        Route::post('staff/employees', [EmployeeController::class, 'store'])->name('staff.employees.store')->middleware('permission:employees.manage');
        Route::put('staff/employees/{employee}', [EmployeeController::class, 'update'])->name('staff.employees.update')->middleware('permission:employees.manage');
        Route::delete('staff/employees/{employee}', [EmployeeController::class, 'destroy'])->name('staff.employees.destroy')->middleware('permission:employees.manage');
        Route::post('staff/users', [UserController::class, 'store'])->name('staff.users.store')->middleware('permission:staff.manage');
        Route::put('staff/users/{user}', [UserController::class, 'update'])->name('staff.users.update')->middleware('permission:staff.manage');
        Route::delete('staff/users/{user}', [UserController::class, 'destroy'])->name('staff.users.destroy')->middleware('permission:staff.manage');

        Route::get('ingredients', [IngredientController::class, 'index'])->name('ingredients.index')->middleware('permission:inventory.view');
        Route::post('ingredients', [IngredientController::class, 'store'])->name('ingredients.store')->middleware('permission:inventory.manage');
        Route::put('ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update')->middleware('permission:inventory.manage');
        Route::delete('ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy')->middleware('permission:inventory.manage');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('permission:inventory.view');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('permission:inventory.manage');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('permission:inventory.manage');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('permission:inventory.manage');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index')->middleware('permission:purchases.manage');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store')->middleware('permission:purchases.manage');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive')->middleware('permission:purchases.manage');

        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index')->middleware('permission:recipes.manage');
        Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store')->middleware('permission:recipes.manage');
        Route::get('recipes/{item}/cost', [RecipeController::class, 'cost'])->name('recipes.cost')->middleware('permission:recipes.manage');

        Route::get('wastage', [WastageController::class, 'index'])->name('wastage.index')->middleware('permission:inventory.view');
        Route::post('wastage', [WastageController::class, 'store'])->name('wastage.store')->middleware('permission:wastage.log');

        Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index')->middleware('permission:inventory.view');
        Route::post('stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store')->middleware('permission:inventory.manage');
        Route::put('stock-counts/{count}/items', [StockCountController::class, 'updateCounts'])->name('stock-counts.update')->middleware('permission:inventory.manage');
        Route::post('stock-counts/{count}/close', [StockCountController::class, 'close'])->name('stock-counts.close')->middleware('permission:inventory.manage');

        Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index')->middleware('permission:transfers.manage');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store')->middleware('permission:transfers.manage');
        Route::post('stock-transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfers.approve')->middleware('permission:transfers.manage');
        Route::post('stock-transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive')->middleware('permission:transfers.manage');

        Route::get('pos', [PosController::class, 'index'])->name('pos.index')->middleware('permission:pos.access');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index')->middleware('permission:orders.view');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store')->middleware('permission:orders.create');
        Route::post('orders/{order}/pay', [OrderController::class, 'pay'])->name('orders.pay')->middleware('permission:orders.create');
        Route::post('orders/{order}/gateway-payment', [PaymentController::class, 'startGateway'])->name('payments.gateway')->middleware('permission:orders.create');
        Route::post('orders/{order}/split', [PaymentController::class, 'split'])->name('payments.split')->middleware('permission:orders.create');
        Route::post('orders/{order}/manual-payment', [PaymentController::class, 'recordManual'])->name('payments.manual')->middleware('permission:orders.create');
        Route::post('payments/{payment}/approve', [PaymentController::class, 'approveManual'])->name('payments.approve')->middleware('permission:payments.manage');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status')->middleware('permission:orders.create');
        Route::post('orders/{order}/void-request', [OrderController::class, 'requestVoid'])->name('orders.void-request')->middleware('permission:orders.create');
        Route::post('orders/{order}/approve-void', [OrderController::class, 'approveVoid'])->name('orders.approve-void')->middleware('permission:orders.void');
        Route::get('orders/{order}/receipt', [ReceiptController::class, 'show'])->name('orders.receipt')->middleware('permission:orders.view');

        Route::post('shifts/open', [ShiftController::class, 'open'])->name('shifts.open')->middleware('permission:shifts.manage');
        Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close')->middleware('permission:shifts.manage');

        Route::get('kds', [KdsController::class, 'index'])->name('kds.index')->middleware('permission:kds.access');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('permission:expenses.manage');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('permission:expenses.manage');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('permission:expenses.manage');

        Route::get('loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index')->middleware('permission:loyalty.manage');
        Route::put('loyalty/settings', [LoyaltyController::class, 'updateSettings'])->name('loyalty.settings')->middleware('permission:loyalty.manage');
        Route::post('loyalty/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust')->middleware('permission:loyalty.manage');
        Route::post('loyalty/redeem', [LoyaltyRedemptionController::class, 'store'])->name('loyalty.redeem')->middleware('permission:loyalty.manage');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index')->middleware('permission:coupons.manage');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store')->middleware('permission:coupons.manage');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update')->middleware('permission:coupons.manage');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy')->middleware('permission:coupons.manage');

        Route::get('delivery', [DeliveryController::class, 'index'])->name('delivery.index')->middleware('permission:delivery.dispatch');
        Route::post('delivery/orders/{order}/assign', [DeliveryController::class, 'assign'])->name('delivery.assign')->middleware('permission:delivery.dispatch');
        Route::post('delivery/{delivery}/reconcile-cod', [DeliveryController::class, 'reconcileCod'])->name('delivery.reconcile-cod')->middleware('permission:delivery.dispatch');

        Route::get('accounting', [AccountingController::class, 'index'])->name('accounting.index')->middleware('permission:accounting.view');
        Route::post('accounting/journals', [AccountingController::class, 'storeManual'])->name('accounting.journals.store')->middleware('permission:journals.post');
        Route::post('accounting/journals/{journal}/approve', [AccountingController::class, 'approve'])->name('accounting.journals.approve')->middleware('permission:journals.post');
        Route::post('accounting/journals/{journal}/reverse', [AccountingController::class, 'reverse'])->name('accounting.journals.reverse')->middleware('permission:journals.post');
        Route::post('accounting/fiscal-years/{year}/lock', [AccountingController::class, 'lockYear'])->name('accounting.years.lock')->middleware('permission:periods.lock');
        Route::get('accounting/export/{statement}', [AccountingController::class, 'export'])->name('accounting.export')->middleware('permission:accounting.view');

        Route::get('aggregators', [AggregatorController::class, 'index'])->name('aggregators.index')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels', [AggregatorController::class, 'storeChannel'])->name('aggregators.channels.store')->middleware('permission:aggregators.manage');
        Route::put('aggregators/channels/{channel}', [AggregatorController::class, 'updateChannel'])->name('aggregators.channels.update')->middleware('permission:aggregators.manage');
        Route::post('aggregators/orders/manual', [AggregatorController::class, 'manualOrder'])->name('aggregators.orders.manual')->middleware('permission:aggregators.manage');
        Route::post('aggregators/orders/{order}/decision', [AggregatorController::class, 'decide'])->name('aggregators.orders.decision')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/payouts', [AggregatorController::class, 'reconcilePayout'])->name('aggregators.payouts')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/menu-sync', [AggregatorController::class, 'syncMenu'])->name('aggregators.menu-sync')->middleware('permission:aggregators.manage');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('permission:reports.view');
    });
