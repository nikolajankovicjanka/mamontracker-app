<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('auth:sanctum');
