<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResourceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

// Публичные маршруты (регистрация и логин)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Защищённые маршруты (требуют токен)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('resources', ResourceController::class)
            ->except(['index', 'show']);   // index и show будут доступны всем авторизованным
    });

    // Просмотр бань доступен всем авторизованным
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/resources/{resource}', [ResourceController::class, 'show']);
});
