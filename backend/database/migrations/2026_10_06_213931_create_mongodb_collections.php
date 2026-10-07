<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat semua collections MongoDB beserta indexes.
 * MongoDB membuat collection otomatis saat insert pertama,
 * tapi migration ini memastikan indexes sudah siap dari awal.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // 1. users
        Schema::connection('mongodb')->create('users', function (Blueprint $collection) {
            $collection->unique('email');
            $collection->index('role');
            $collection->index('created_at');
        });

        // 2. products
        Schema::connection('mongodb')->create('products', function (Blueprint $collection) {
            $collection->index('category');
            $collection->index('brand');
            $collection->index('name');
            $collection->index('price');
            $collection->index('stock');
        });

        // 3. promotions
        Schema::connection('mongodb')->create('promotions', function (Blueprint $collection) {
            $collection->index('start_date');
            $collection->index('end_date');
            $collection->index('brands');
        });

        // 4. carts
        Schema::connection('mongodb')->create('carts', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('session_id');
            $collection->index('updated_at');
        });

        // 5. cart_items
        Schema::connection('mongodb')->create('cart_items', function (Blueprint $collection) {
            $collection->index('cart_id');
            $collection->index('product_id');
            $collection->index('build_id');
            $collection->index('source_type');
        });

        // 6. orders
        Schema::connection('mongodb')->create('orders', function (Blueprint $collection) {
            $collection->unique('order_number');
            $collection->index('user_id');
            $collection->index('status');
            $collection->index('created_at');
        });

        // 7. order_items
        Schema::connection('mongodb')->create('order_items', function (Blueprint $collection) {
            $collection->index('order_id');
            $collection->index('product_id');
            $collection->index('build_id');
            $collection->index('source_type');
        });

        // 8. payments
        Schema::connection('mongodb')->create('payments', function (Blueprint $collection) {
            $collection->index('order_id');
            $collection->index('status');
            $collection->index('gateway_provider');
            $collection->unique('gateway_transaction_id');
            $collection->index('paid_at');
        });

        // 9. prebuilt_packages
        Schema::connection('mongodb')->create('prebuilt_packages', function (Blueprint $collection) {
            $collection->index('tag');
        });

        // 10. package_items
        Schema::connection('mongodb')->create('package_items', function (Blueprint $collection) {
            $collection->index('package_id');
            $collection->index('product_id');
        });
    }

    public function down(): void
    {
        $collections = [
            'users', 'products', 'promotions',
            'carts', 'cart_items',
            'orders', 'order_items',
            'payments',
            'prebuilt_packages', 'package_items',
        ];

        foreach ($collections as $col) {
            Schema::connection('mongodb')->dropIfExists($col);
        }
    }
};
