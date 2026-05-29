<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\GpsDeviceController;
use App\Http\Controllers\Api\VehicleServiceController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleAssignmentController;
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

    Route::get('/gps-devices', [GpsDeviceController::class, 'index'])
        ->middleware('tenant.feature:gps_devices');

    Route::get('/gps-devices/{gpsDevice}', [GpsDeviceController::class, 'show'])
        ->middleware('tenant.feature:gps_devices');

    Route::post('/gps-devices', [GpsDeviceController::class, 'store'])
        ->middleware('tenant.feature:gps_devices');

    Route::put('/gps-devices/{gpsDevice}', [GpsDeviceController::class, 'update'])
        ->middleware('tenant.feature:gps_devices');

    Route::delete('/gps-devices/{gpsDevice}', [GpsDeviceController::class, 'destroy'])
        ->middleware('tenant.feature:gps_devices');

    Route::get('/services', [VehicleServiceController::class, 'index'])
        ->middleware('tenant.feature:services');

    Route::get('/services/{service}', [VehicleServiceController::class, 'show'])
        ->middleware('tenant.feature:services');

    Route::post('/services', [VehicleServiceController::class, 'store'])
        ->middleware('tenant.feature:services');

    Route::put('/services/{service}', [VehicleServiceController::class, 'update'])
        ->middleware('tenant.feature:services');

    Route::delete('/services/{service}', [VehicleServiceController::class, 'destroy'])
        ->middleware('tenant.feature:services');

    Route::get('/registrations', [RegistrationController::class, 'index'])
        ->middleware('tenant.feature:registrations');

    Route::get('/registrations/{vehicle}', [RegistrationController::class, 'show'])
        ->middleware('tenant.feature:registrations');

    Route::put('/registrations/{vehicle}', [RegistrationController::class, 'update'])
        ->middleware('tenant.feature:registrations');

    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/unread-count', [AlertController::class, 'unreadCount']);
    Route::post('/alerts/read-all', [AlertController::class, 'markAllAsRead']);
    Route::post('/alerts/{alert}/read', [AlertController::class, 'markAsRead']);

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('tenant.feature:users');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('tenant.feature:users');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('tenant.feature:users');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('tenant.feature:users');

    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])
        ->middleware('tenant.feature:users');

    Route::get('/vehicle-assignments', [VehicleAssignmentController::class, 'index'])
        ->middleware(['tenant.feature:users', 'tenant.feature:vehicles']);

    Route::get('/vehicle-assignments/{vehicleAssignment}', [VehicleAssignmentController::class, 'show'])
        ->middleware(['tenant.feature:users', 'tenant.feature:vehicles']);

    Route::post('/vehicle-assignments', [VehicleAssignmentController::class, 'store'])
        ->middleware(['tenant.feature:users', 'tenant.feature:vehicles']);

    Route::put('/vehicle-assignments/{vehicleAssignment}', [VehicleAssignmentController::class, 'update'])
        ->middleware(['tenant.feature:users', 'tenant.feature:vehicles']);

    Route::post('/vehicle-assignments/{vehicleAssignment}/end', [VehicleAssignmentController::class, 'end'])
        ->middleware(['tenant.feature:users', 'tenant.feature:vehicles']);
});
