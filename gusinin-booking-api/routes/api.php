<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResourceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;

// Регистрация и логин
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Защищённые маршруты (требуют токен)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Бронь
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('resources', ResourceController::class)
            ->except(['index', 'show']);
    });

    // Просмотр бань доступен всем авторизованным
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/resources/{resource}', [ResourceController::class, 'show']);
    Route::get('/resources/{resource}/schedule', [ResourceController::class, 'schedule']);
    Route::get('/resources/search', [ResourceController::class, 'search']);
    Route::get('/resources/{resource}/schedule/week', [ResourceController::class, 'scheduleWeek']);

    // Просмотр отзывов на бани
    Route::get('/resources/{resourceId}/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
});
