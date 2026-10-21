<?php

use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AggregatorController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceDeviceController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchSwitchController;
use App\Http\Controllers\Admin\CareerController as AdminCareerController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\KdsController;
use App\Http\Controllers\Admin\LetterheadController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\LoyaltyRedemptionController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RestaurantTableController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\StockCountController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SyncConflictController;
use App\Http\Controllers\Admin\UiKitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WastageController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Payments\GatewayCallbackController;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Rider\RiderLocationWebController;
use App\Http\Controllers\Web\AboutController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AggregatorWebhookController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\CareerController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\CustomerAuthController;
use App\Http\Controllers\Web\CustomerGoogleController;
use App\Http\Controllers\Web\DemoSessionController;
use App\Http\Controllers\Web\FaqController;
use App\Http\Controllers\Web\GalleryController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ItemController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\TrackController;
use App\Http\Controllers\Web\WebPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('web.home');
Route::get('/about', AboutController::class)->name('web.about');
Route::get('/gallery', GalleryController::class)->name('web.gallery');
Route::get('/blog', [BlogController::class, 'index'])->name('web.blog.index');
Route::get('/blog/feed.xml', [BlogController::class, 'feed'])->name('web.blog.feed');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('web.blog.show');
Route::get('/careers', [CareerController::class, 'index'])->name('web.careers.index');
Route::post('/careers/apply', [CareerController::class, 'apply'])->name('web.careers.apply')->middleware('throttle:5,60');
Route::get('/careers/{opening}/{slug?}', [CareerController::class, 'show'])->whereNumber('opening')->name('web.careers.show');
Route::get('/contact', ContactController::class)->name('web.contact');
Route::get('/locations', [LocationController::class, 'index'])->name('web.locations.index');
Route::get('/locations/{slug}', [LocationController::class, 'show'])->name('web.locations.show');
Route::get('/menu/{branchCode}/items/{item}', [ItemController::class, 'show'])->name('web.item');
Route::get('/menu/{branchCode?}', MenuController::class)->name('web.menu');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('web.checkout');
Route::post('/checkout', [CheckoutController::class, 'place'])->name('web.checkout.place');
Route::post('/coupons/validate', [CheckoutController::class, 'validateCoupon'])->name('web.coupons.validate')->middleware('throttle:20,1');
Route::get('/track/{token}', [TrackController::class, 'show'])->name('web.track');
Route::get('/pay/{order}/{gateway}', [WebPaymentController::class, 'start'])->name('web.payment.start');

// QR table ordering: scanning the code opens the menu for that table.
Route::get('/q/{table}', fn (string $table) => redirect()->route('web.menu', ['table' => $table]))->name('web.qr');
Route::get('/q/{branchCode}/{table}', fn (string $branchCode, string $table) => redirect()->route('web.menu', [
    'branchCode' => strtolower($branchCode),
    'table' => $table,
]))->name('web.qr.branch');

Route::get('/customer/login', [CustomerAuthController::class, 'showLogin'])->name('web.customer.login');
Route::post('/customer/otp/send', [CustomerAuthController::class, 'sendOtp'])->name('web.customer.otp.send');
Route::post('/customer/otp/verify', [CustomerAuthController::class, 'verifyOtp'])->name('web.customer.otp.verify');
Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('web.customer.logout');
Route::get('/customer/auth/google/redirect', [CustomerGoogleController::class, 'redirect'])->name('web.customer.google.redirect');
Route::get('/customer/auth/google/callback', [CustomerGoogleController::class, 'callback'])->name('web.customer.google.callback');

Route::middleware('auth:customer')->group(function (): void {
    Route::get('/account', AccountController::class)->name('web.account');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('web.account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('web.account.addresses.destroy');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('web.account.destroy');
});

// Rider web view (staff guard, rider role only, mobile-first).
Route::prefix('rider')->name('rider.')->middleware(['auth'])->group(function (): void {
    Route::get('/', [RiderController::class, 'index'])->name('index');
    Route::post('deliveries/{delivery}/status', [RiderController::class, 'updateStatus'])->name('status');
    Route::post('duty', [RiderController::class, 'toggleDuty'])->name('duty');
    Route::post('location', [RiderLocationWebController::class, 'store'])->name('location');
});

Route::get('/privacy', [LegalController::class, 'privacy'])->name('web.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('web.terms');
Route::get('/refund-policy', [LegalController::class, 'refund'])->name('web.refund');
Route::get('/delivery-policy', [LegalController::class, 'delivery'])->name('web.delivery');
Route::get('/cookie-policy', [LegalController::class, 'cookies'])->name('web.cookies');
Route::get('/faq', FaqController::class)->name('web.faq');

Route::get('/sitemap.xml', SitemapController::class)->name('web.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('web.robots');

Route::post('/locale', LocaleController::class)->name('locale.switch');

// Demo sites only: visitor session started with a mobile number, and its journey events.
Route::prefix('demo')->name('demo.')->group(function (): void {
    Route::post('session', [DemoSessionController::class, 'start'])->name('start')->middleware('throttle:10,1');
    Route::post('events', [DemoSessionController::class, 'events'])->name('events')->middleware('throttle:120,1');
    Route::delete('session', [DemoSessionController::class, 'end'])->name('end');
});

// Aggregator webhooks: public, one per channel, CSRF-excluded.
Route::post('/webhooks/aggregators/{key}', AggregatorWebhookController::class)->name('aggregators.webhook');

// Gateway callbacks and IPN are public web routes (CSRF-excluded).
Route::get('/payments/bkash/callback', [GatewayCallbackController::class, 'bkashCallback'])->name('payments.bkash.callback');
Route::post('/payments/sslcommerz/ipn', [GatewayCallbackController::class, 'sslcommerzIpn'])->name('payments.sslcommerz.ipn');
Route::get('/payments/sslcommerz/return', [GatewayCallbackController::class, 'sslcommerzReturn'])->name('payments.sslcommerz.return');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
    Route::post('/login/demo', [LoginController::class, 'demo'])->name('login.demo');

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

        Route::get('blog', [AdminBlogController::class, 'index'])->name('blog.index')->middleware('permission:blog.manage');
        Route::post('blog', [AdminBlogController::class, 'store'])->name('blog.store')->middleware('permission:blog.manage');
        Route::put('blog/{post}', [AdminBlogController::class, 'update'])->name('blog.update')->middleware('permission:blog.manage');
        Route::delete('blog/{post}', [AdminBlogController::class, 'destroy'])->name('blog.destroy')->middleware('permission:blog.manage');

        Route::get('careers', [AdminCareerController::class, 'index'])->name('careers.index')->middleware('permission:careers.manage');
        Route::post('careers', [AdminCareerController::class, 'store'])->name('careers.store')->middleware('permission:careers.manage');
        Route::put('careers/{opening}', [AdminCareerController::class, 'update'])->name('careers.update')->middleware('permission:careers.manage');
        Route::delete('careers/{opening}', [AdminCareerController::class, 'destroy'])->name('careers.destroy')->middleware('permission:careers.manage');
        Route::put('careers/applications/{application}', [AdminCareerController::class, 'updateApplication'])->name('careers.applications.update')->middleware('permission:careers.manage');
        Route::delete('careers/applications/{application}', [AdminCareerController::class, 'destroyApplication'])->name('careers.applications.destroy')->middleware('permission:careers.manage');
        Route::get('careers/applications/{application}/cv', [AdminCareerController::class, 'downloadCv'])->name('careers.applications.cv')->middleware('permission:careers.manage');

        Route::get('media', [AdminMediaController::class, 'index'])->name('media.index')->middleware('permission:media.view');
        Route::post('media', [AdminMediaController::class, 'store'])->name('media.store')->middleware('permission:media.manage');
        Route::put('media/{media}', [AdminMediaController::class, 'update'])->name('media.update')->middleware('permission:media.manage');
        Route::post('media/{media}/move', [AdminMediaController::class, 'move'])->name('media.move')->middleware('permission:media.manage');
        Route::delete('media/{media}', [AdminMediaController::class, 'destroy'])->name('media.destroy')->middleware('permission:media.manage');

        Route::get('menu', [AdminMenuController::class, 'index'])->name('menu.index')->middleware('permission:menu.view');
        Route::post('menu/categories', [AdminMenuController::class, 'storeCategory'])->name('menu.categories.store')->middleware('permission:menu.manage');
        Route::put('menu/categories/{category}', [AdminMenuController::class, 'updateCategory'])->name('menu.categories.update')->middleware('permission:menu.manage');
        Route::delete('menu/categories/{category}', [AdminMenuController::class, 'destroyCategory'])->name('menu.categories.destroy')->middleware('permission:menu.manage');
        Route::post('menu/items', [AdminMenuController::class, 'storeItem'])->name('menu.items.store')->middleware('permission:menu.manage');
        Route::put('menu/items/{item}', [AdminMenuController::class, 'updateItem'])->name('menu.items.update')->middleware('permission:menu.manage');
        Route::delete('menu/items/{item}', [AdminMenuController::class, 'destroyItem'])->name('menu.items.destroy')->middleware('permission:menu.manage');

        Route::get('tables', [RestaurantTableController::class, 'index'])->name('tables.index')->middleware('permission:tables.view');
        Route::post('tables', [RestaurantTableController::class, 'store'])->name('tables.store')->middleware('permission:tables.manage');
        Route::put('tables/{table}', [RestaurantTableController::class, 'update'])->name('tables.update')->middleware('permission:tables.manage');
        Route::delete('tables/{table}', [RestaurantTableController::class, 'destroy'])->name('tables.destroy')->middleware('permission:tables.manage');

        Route::get('staff', [EmployeeController::class, 'index'])->name('staff.index')->middleware('permission:staff.view');
        Route::post('staff/employees', [EmployeeController::class, 'store'])->name('staff.employees.store')->middleware('permission:employees.manage');
        Route::put('staff/employees/{employee}', [EmployeeController::class, 'update'])->name('staff.employees.update')->middleware('permission:employees.manage');
        Route::delete('staff/employees/{employee}', [EmployeeController::class, 'destroy'])->name('staff.employees.destroy')->middleware('permission:employees.manage');
        Route::post('staff/users', [UserController::class, 'store'])->name('staff.users.store')->middleware('permission:staff.manage');
        Route::put('staff/users/{user}', [UserController::class, 'update'])->name('staff.users.update')->middleware('permission:staff.manage');
        Route::delete('staff/users/{user}', [UserController::class, 'destroy'])->name('staff.users.destroy')->middleware('permission:staff.manage');

        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index')->middleware('permission:attendance.manage');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:attendance.manage');
        Route::post('attendance/devices', [AttendanceDeviceController::class, 'store'])->name('attendance.devices.store')->middleware('permission:attendance.manage');
        Route::put('attendance/devices/{device}', [AttendanceDeviceController::class, 'update'])->name('attendance.devices.update')->middleware('permission:attendance.manage');
        Route::post('attendance/devices/{device}/token', [AttendanceDeviceController::class, 'rotate'])->name('attendance.devices.token')->middleware('permission:attendance.manage');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:payroll.manage');
        Route::post('payroll/advances', [PayrollController::class, 'storeAdvance'])->name('payroll.advances.store')->middleware('permission:payroll.manage');
        Route::get('payroll/slips/{employee}', [PayrollController::class, 'slip'])->name('payroll.slip')->middleware('permission:payroll.manage');

        Route::get('letters', [LetterheadController::class, 'index'])->name('letters.index')->middleware('permission:letters.manage');
        Route::post('letters', [LetterheadController::class, 'store'])->name('letters.store')->middleware('permission:letters.manage');
        Route::put('letters/{letter}', [LetterheadController::class, 'update'])->name('letters.update')->middleware('permission:letters.manage');
        Route::delete('letters/{letter}', [LetterheadController::class, 'destroy'])->name('letters.destroy')->middleware('permission:letters.manage');
        Route::get('letters/{letter}/print', [LetterheadController::class, 'print'])->name('letters.print')->middleware('permission:letters.manage');

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
        Route::post('pos/unlock', [PosController::class, 'unlock'])->name('pos.unlock')->middleware('permission:pos.access');
        Route::post('pos/tables/{table}/release', [PosController::class, 'releaseTable'])->name('pos.tables.release')->middleware('permission:pos.access');
        Route::post('pos/orders/{order}/clear', [PosController::class, 'clearOrder'])->name('pos.orders.clear')->middleware('permission:pos.access');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index')->middleware('permission:orders.view');
        Route::get('orders/incoming', [OrderController::class, 'incoming'])->name('orders.incoming')->middleware('permission:orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store')->middleware('permission:orders.create');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update')->middleware('permission:orders.create');
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
        Route::put('accounting/accounts/{account}', [AccountingController::class, 'updateAccount'])->name('accounting.accounts.update')->middleware('permission:journals.post');
        Route::post('accounting/vouchers', [AccountingController::class, 'storeVoucher'])->name('accounting.vouchers.store')->middleware('permission:vouchers.manage');
        Route::post('accounting/statements', [AccountingController::class, 'storeStatement'])->name('accounting.statements.store')->middleware('permission:journals.post');
        Route::post('accounting/statements/{line}/match', [AccountingController::class, 'matchStatement'])->name('accounting.statements.match')->middleware('permission:journals.post');
        Route::post('accounting/journals', [AccountingController::class, 'storeManual'])->name('accounting.journals.store')->middleware('permission:journals.post');
        Route::post('accounting/journals/{journal}/approve', [AccountingController::class, 'approve'])->name('accounting.journals.approve')->middleware('permission:journals.post');
        Route::post('accounting/journals/{journal}/reverse', [AccountingController::class, 'reverse'])->name('accounting.journals.reverse')->middleware('permission:journals.post');
        Route::post('accounting/fiscal-years/{year}/lock', [AccountingController::class, 'lockYear'])->name('accounting.years.lock')->middleware('permission:periods.lock');
        Route::get('accounting/export/{statement}', [AccountingController::class, 'export'])->name('accounting.export')->middleware('permission:accounting.view');

        Route::get('aggregators', [AggregatorController::class, 'index'])->name('aggregators.index')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/toggle', [AggregatorController::class, 'toggleChannel'])->name('aggregators.channels.toggle')->middleware('permission:aggregators.manage');
        Route::put('aggregators/channels/{channel}', [AggregatorController::class, 'updateChannel'])->name('aggregators.channels.update')->middleware('permission:aggregators.manage');
        Route::post('aggregators/orders/manual', [AggregatorController::class, 'manualOrder'])->name('aggregators.orders.manual')->middleware('permission:aggregators.manage');
        Route::post('aggregators/orders/{order}/decision', [AggregatorController::class, 'decide'])->name('aggregators.orders.decision')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/payouts', [AggregatorController::class, 'reconcilePayout'])->name('aggregators.payouts')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/menu-sync', [AggregatorController::class, 'syncMenu'])->name('aggregators.menu-sync')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/connection-test', [AggregatorController::class, 'testConnection'])->name('aggregators.connection-test')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/outlet-status', [AggregatorController::class, 'outletStatus'])->name('aggregators.outlet-status')->middleware('permission:aggregators.manage');
        Route::post('aggregators/channels/{channel}/import-orders', [AggregatorController::class, 'importOrders'])->name('aggregators.import-orders')->middleware('permission:aggregators.manage');

        Route::get('sync-conflicts', [SyncConflictController::class, 'index'])->name('sync.conflicts')->middleware('permission:sync.resolve');
        Route::post('sync-conflicts/{item}/discard', [SyncConflictController::class, 'discard'])->name('sync.discard')->middleware('permission:sync.resolve');
        Route::post('sync-conflicts/{item}/retry', [SyncConflictController::class, 'retry'])->name('sync.retry')->middleware('permission:sync.resolve');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('permission:customers.view');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index')->middleware('permission:settings.manage');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:settings.manage');

        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity.index')->middleware('permission:activity.view');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('permission:reports.view');
    });
