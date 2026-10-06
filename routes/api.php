<?php

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

// Users
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);

// Products (Katalog, Filter, Search, CRUD)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

// Prebuilt PC Packages
Route::get('/packages', [PrebuiltPackageController::class, 'index']);
Route::get('/packages/{id}', [PrebuiltPackageController::class, 'show']);

// Promotions
Route::get('/promotions', [PromotionController::class, 'index']);

// Shopping Cart (Catalog items & PC Builder builds)
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
Route::post('/cart/clear', [CartController::class, 'clear']);

// Orders & Checkout
Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::post('/orders/checkout', [OrderController::class, 'checkout']);

// PC Builder Engine (Auto-matching, Compatibility & Wattage)
Route::post('/builder/validate', [\App\Http\Controllers\Api\PCBuilderController::class, 'validateBuild']);
Route::post('/builder/add-to-cart', [\App\Http\Controllers\Api\PCBuilderController::class, 'addBuildToCart']);

// Payment Gateway Simulation
Route::post('/payments/{orderId}/pay', [PaymentController::class, 'pay']);
