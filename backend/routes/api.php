<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PCBuilderController;
use App\Http\Controllers\Api\PrebuiltPackageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Health check & Directory API
 */
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'app' => 'PC Store E-Commerce API (MongoDB NoSQL)',
        'version' => '1.0.0',
        'endpoints' => [
            'products' => '/api/products',
            'categories' => '/api/categories',
            'brands' => '/api/brands',
            'packages' => '/api/packages',
            'promotions' => '/api/promotions',
            'promo-codes' => '/api/promo-codes/validate',
            'reviews' => '/api/reviews',
            'addresses' => '/api/addresses',
            'users' => '/api/users',
            'cart' => '/api/cart',
            'orders' => '/api/orders',
            'payments' => '/api/payments',
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

// Users (baca: admin/superadmin/keuangan; Manajemen Akun: superadmin)
Route::get('/users', [UserController::class, 'index'])->middleware(['auth.token', 'admin.read']);
Route::get('/users/{id}', [UserController::class, 'show'])->middleware(['auth.token', 'admin.read']);
Route::patch('/users/{id}/role', [UserController::class, 'setRole'])->middleware(['auth.token', 'superadmin']);
Route::post('/admin/users', [UserController::class, 'store'])->middleware(['auth.token', 'superadmin']);
Route::put('/admin/users/{id}', [UserController::class, 'update'])->middleware(['auth.token', 'superadmin']);
Route::delete('/admin/users/{id}', [UserController::class, 'destroy'])->middleware(['auth.token', 'superadmin']);

// Products (baca publik, tulis admin only)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/products/{id}', [ProductController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Categories & brands (baca publik, tulis admin)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{idOrSlug}', [CategoryController::class, 'show']);
Route::get('/admin/categories', [CategoryController::class, 'adminIndex'])->middleware(['auth.token', 'admin.read']);
Route::post('/categories', [CategoryController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware(['auth.token', 'admin']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/admin/brands', [BrandController::class, 'adminIndex'])->middleware(['auth.token', 'admin.read']);
Route::post('/brands', [BrandController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/brands/{id}', [BrandController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/brands/{id}', [BrandController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Reviews (baca publik, tulis user login, hapus admin)
Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store'])->middleware('auth.token');
Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Addresses (login atau guest session_id; owner mencegah spoof user_id)
Route::get('/addresses', [AddressController::class, 'index'])->middleware('owner');
Route::post('/addresses', [AddressController::class, 'store'])->middleware('owner');
Route::put('/addresses/{id}', [AddressController::class, 'update'])->middleware('owner');
Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->middleware('owner');

// Prebuilt PC Packages (baca publik, tulis + beli admin/user)
Route::get('/packages', [PrebuiltPackageController::class, 'index']);
Route::get('/packages/{id}', [PrebuiltPackageController::class, 'show']);
Route::post('/packages/{id}/add-to-cart', [PrebuiltPackageController::class, 'addToCart'])->middleware('owner');
Route::post('/packages', [PrebuiltPackageController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/packages/{id}', [PrebuiltPackageController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/packages/{id}', [PrebuiltPackageController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Promotions (baca publik aktif, tulis admin)
Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/admin/promotions', [PromotionController::class, 'adminIndex'])->middleware(['auth.token', 'admin.read']);
Route::get('/promotions/{id}', [PromotionController::class, 'show']);
Route::get('/promotions/{id}/products', [PromotionController::class, 'products']);
Route::post('/promotions', [PromotionController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/promotions/{id}', [PromotionController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/promotions/{id}', [PromotionController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Promo codes (validasi publik dengan session_id; CRUD admin)
Route::post('/promo-codes/validate', [PromoCodeController::class, 'validateCode'])->middleware('owner');
Route::get('/admin/promo-codes', [PromoCodeController::class, 'index'])->middleware(['auth.token', 'admin.read']);
Route::post('/promo-codes', [PromoCodeController::class, 'store'])->middleware(['auth.token', 'admin']);
Route::put('/promo-codes/{id}', [PromoCodeController::class, 'update'])->middleware(['auth.token', 'admin']);
Route::delete('/promo-codes/{id}', [PromoCodeController::class, 'destroy'])->middleware(['auth.token', 'admin']);

// Admin dashboard stats (baca operasional: admin/superadmin/keuangan)
Route::get('/admin/stats', [StatsController::class, 'index'])->middleware(['auth.token', 'admin.read']);
Route::get('/admin/top-products', [StatsController::class, 'topProducts'])->middleware(['auth.token', 'admin.read']);

// Payment logs & reconciliation (admin/keuangan)
Route::get('/admin/payment-logs', [PaymentController::class, 'logs'])->middleware(['auth.token', 'admin.read']);
Route::get('/admin/payment-logs/{id}', [PaymentController::class, 'logDetail'])->middleware(['auth.token', 'admin.read']);

// Laporan keuangan (keuangan/superadmin)
Route::get('/admin/finance/profit-loss', [FinanceController::class, 'profitLoss'])->middleware(['auth.token', 'finance']);
Route::get('/admin/finance/tax', [FinanceController::class, 'tax'])->middleware(['auth.token', 'finance']);
Route::get('/admin/finance/margin', [FinanceController::class, 'margin'])->middleware(['auth.token', 'finance']);

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

// PC Builder Engine (auto-matching, kompatibilitas & wattage)
Route::post('/builder/validate', [PCBuilderController::class, 'validateBuild']);
Route::post('/builder/add-to-cart', [PCBuilderController::class, 'addBuildToCart'])->middleware('owner');
Route::put('/builder/builds/{buildId}', [PCBuilderController::class, 'replaceBuild'])->middleware('owner');

// Payment Gateway Simulation & Midtrans
Route::post('/payments/{orderId}/pay', [PaymentController::class, 'pay']);
Route::post('/payments/{orderId}/snap-token', [PaymentController::class, 'snapToken']);
Route::get('/payments/{orderId}/instructions', [PaymentController::class, 'instructions']);
Route::post('/payments/callback', [PaymentController::class, 'callback']);
