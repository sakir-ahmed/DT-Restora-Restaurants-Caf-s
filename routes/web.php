<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchSwitchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\KdsController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RecipeController;
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
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome/Welcome')->name('home');

Route::post('/locale', LocaleController::class)->name('locale.switch');

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

        Route::get('menu', [MenuController::class, 'index'])->name('menu.index')->middleware('permission:menu.view');
        Route::post('menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store')->middleware('permission:menu.manage');
        Route::put('menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('menu.categories.update')->middleware('permission:menu.manage');
        Route::delete('menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy')->middleware('permission:menu.manage');
        Route::post('menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store')->middleware('permission:menu.manage');
        Route::put('menu/items/{item}', [MenuController::class, 'updateItem'])->name('menu.items.update')->middleware('permission:menu.manage');
        Route::delete('menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy')->middleware('permission:menu.manage');

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
    });
