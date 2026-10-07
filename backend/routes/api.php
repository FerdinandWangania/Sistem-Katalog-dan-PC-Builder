<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PrebuiltPackageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Health check & Directory API
 */
Route::get('/', function () {
    return response()->json([
        'status'    => 'success',
        'app'       => 'PC Store E-Commerce API (MongoDB NoSQL)',
        'version'   => '1.0.0',
        'endpoints' => [
            'products'   => '/api/products',
            'packages'   => '/api/packages',
            'promotions' => '/api/promotions',
            'users'      => '/api/users',
            'cart'       => '/api/cart',
            'orders'     => '/api/orders',
            'payments'   => '/api/payments',
        ],
    ]);
});

// Auth (register/login/forgot dibatasi rate-limit)
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1,register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1,login');
Route::post('/login/google', [AuthController::class, 'loginWithGoogle'])->middleware('throttle:10,1,google-login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1,forgot');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1,reset');
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.token');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth.token');
Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('auth.token');

// Users (admin only)
Route::get('/users', [UserController::class, 'index'])->middleware(['auth.token', 'admin']);
Route::get('/users/{id}', [UserController::class, 'show'])->middleware(['auth.token', 'admin']);
Route::patch('/users/{id}/role', [UserController::class, 'setRole'])->middleware(['auth.token', 'admin']);

// Products (baca publik, tulis admin only)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/products/{id}', [ProductController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Prebuilt PC Packages
Route::get('/packages', [PrebuiltPackageController::class, 'index']);
Route::get('/packages/{id}', [PrebuiltPackageController::class, 'show']);

// Promotions
Route::get('/promotions', [PromotionController::class, 'index']);

// Shopping Cart (user_id wajib milik token; guest pakai session_id)
Route::get('/cart', [CartController::class, 'show'])->middleware('owner');
Route::post('/cart/items', [CartController::class, 'addItem'])->middleware('owner');
Route::delete('/cart/items/{id}', [CartController::class, 'removeItem'])->middleware('owner');
Route::post('/cart/clear', [CartController::class, 'clear'])->middleware('owner');

// Orders & Checkout
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::post('/orders/checkout', [OrderController::class, 'checkout'])->middleware('owner');
Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->middleware(['auth.token', 'admin']);

// PC Builder Engine (Auto-matching, Compatibility & Wattage)
Route::post('/builder/validate', [\App\Http\Controllers\Api\PCBuilderController::class, 'validateBuild']);
Route::post('/builder/add-to-cart', [\App\Http\Controllers\Api\PCBuilderController::class, 'addBuildToCart'])->middleware('owner');

// Payment Gateway Simulation
Route::post('/payments/{orderId}/pay', [PaymentController::class, 'pay']);
