<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/plans', [SubscriptionController::class, 'getPlans']);
Route::post('/payment/callback', [\App\Http\Controllers\Api\PaymentController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/subscriptions/join', [SubscriptionController::class, 'join']);
    Route::get('/payment/status/{uuid}', [\App\Http\Controllers\Api\PaymentController::class, 'checkStatus']);
});
