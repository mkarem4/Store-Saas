<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::get('types', [BusinessController::class, 'types']);
    Route::get('businesses', [BusinessController::class, 'index']);
    Route::get('businesses/{business:slug}', [BusinessController::class, 'show']);
    Route::get('businesses/{business:slug}/products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('auth:customer')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::delete('me', [ProfileController::class, 'destroy']);
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);

        Route::apiResource('addresses', CustomerAddressController::class)->except(['show']);

        Route::post('orders/quote', [OrderController::class, 'quote'])->middleware('throttle:20,1');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{number}', [OrderController::class, 'show']);
        Route::post('orders/{number}/cancel', [OrderController::class, 'cancel']);

        Route::post('devices', [ProfileController::class, 'storeDevice']);
    });
});
