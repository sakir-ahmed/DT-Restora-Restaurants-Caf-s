<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Rider\RiderApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'data' => ['status' => 'ok'],
    ]))->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/otp/send', [OtpController::class, 'send'])->name('otp.send');
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::prefix('rider')->name('rider.')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/deliveries', [RiderApiController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{delivery}/status', [RiderApiController::class, 'updateStatus'])->name('status');
        Route::post('/location', [RiderApiController::class, 'location'])->name('location');
        Route::get('/cod-summary', [RiderApiController::class, 'codSummary'])->name('cod-summary');
    });
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum')
    ->name('api.user');
