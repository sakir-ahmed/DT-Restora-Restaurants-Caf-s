<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchSwitchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\UiKitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome/Welcome')->name('home');

Route::post('/locale', LocaleController::class)->name('locale.switch');

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
    });
