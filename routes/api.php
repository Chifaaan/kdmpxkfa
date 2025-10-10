<?php

use App\Http\Controllers\Api\SsoController;
use App\Http\Controllers\Api\SsoFrontendController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes

// Cooperation routes for cascade selection
Route::prefix('cooperation')->group(function () {
    Route::get('/provinces', [App\Http\Controllers\CooperationController::class, 'provinces']);
    Route::get('/cities/{province}', [App\Http\Controllers\CooperationController::class, 'cities']);
    Route::get('/districts/{province}/{city}', [App\Http\Controllers\CooperationController::class, 'districts']);
    Route::get('/villages/{province}/{city}/{district}', [App\Http\Controllers\CooperationController::class, 'villages']);
    Route::get('/names/{province}/{city}/{district}/{village}', [App\Http\Controllers\CooperationController::class, 'namesByVillage']);
});
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('sso/decrypt', [SsoController::class, 'decrypt']);

        // Frontend SSO validation routes
        Route::get('sso/config', [SsoFrontendController::class, 'getConfig']);
        Route::post('sso/login', [SsoController::class, 'login']);
    });

    Route::get('/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);

    // Public product routes
    //    Route::get('products', [DashboardController::class, 'index']);
    //    Route::get('products/{product}', [DashboardController::class, 'show']);
});

// Protected routes
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    // Authentication management
    Route::prefix('auth')->group(function () {
        Route::get('me', [SsoController::class, 'me']);
        Route::post('logout', [SsoController::class, 'logout']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('orders/{order}/pay', [PaymentController::class, 'createTransaction'])->name('payment.create');
    });
    Route::post('/payment/notification', [PaymentController::class, 'handleNotification'])->name('payment.notification');

    // Onboarding routes (accessible even without completed onboarding)
    //    Route::prefix('onboarding')->group(function () {
    //        Route::get('status', [OnboardingController::class, 'status']);
    //        Route::post('complete', [OnboardingController::class, 'complete']);
    //    });
    //
    //    // Routes requiring completed onboarding
    //    Route::middleware(['check.onboarding'])->group(function () {
    //        // User profile management
    //        Route::prefix('profile')->group(function () {
    //            Route::get('/', [ProfileController::class, 'show']);
    //            Route::put('/', [ProfileController::class, 'update']);
    //            Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
    //        });
    //
    //        // Cart management
    //        Route::prefix('cart')->group(function () {
    //            Route::get('/', [CartController::class, 'index']);
    //            Route::post('items', [CartController::class, 'addItem']);
    //            Route::put('items/{item}', [CartController::class, 'updateItem']);
    //            Route::delete('items/{item}', [CartController::class, 'removeItem']);
    //            Route::delete('clear', [CartController::class, 'clear']);
    //        });
    //
    //        // Order management
    //        Route::prefix('orders')->group(function () {
    //            Route::get('/', [OrderController::class, 'index']);
    //            Route::post('/', [OrderController::class, 'store']);
    //            Route::get('{order}', [OrderController::class, 'show']);
    //            Route::put('{order}/cancel', [OrderController::class, 'cancel']);
    //        });
    //    });
});
