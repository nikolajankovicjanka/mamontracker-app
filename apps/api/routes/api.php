<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('tenant.feature:dashboard');

    Route::get('/vehicles', [VehicleController::class, 'index'])
        ->middleware('tenant.feature:vehicles');

    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
        ->middleware('tenant.feature:vehicles');

    Route::post('/vehicles', [VehicleController::class, 'store'])
        ->middleware('tenant.feature:vehicles');

    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])
        ->middleware('tenant.feature:vehicles');

    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
        ->middleware('tenant.feature:vehicles');
});
