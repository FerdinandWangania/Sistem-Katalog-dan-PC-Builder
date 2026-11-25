<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Koleksi dan index untuk P2: kategori, brand, alamat, ulasan, kode promo,
 * log pembayaran, serta index tambahan pada promotions/orders/payments.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('categories', function (Blueprint $collection) {
            $collection->unique('slug');
            $collection->index('name');
            $collection->index('type');
            $collection->index('is_active');
        });

        Schema::connection('mongodb')->create('brands', function (Blueprint $collection) {
            $collection->unique('name');
            $collection->index('is_active');
        });

        Schema::connection('mongodb')->create('addresses', function (Blueprint $collection) {
            $collection->index(['user_id', 'is_default']);
            $collection->index(['session_id', 'is_default']);
            $collection->index('created_at');
        });

        Schema::connection('mongodb')->create('reviews', function (Blueprint $collection) {
            $collection->unique(['product_id', 'user_id']);
            $collection->index('product_id');
            $collection->index('user_id');
            $collection->index('rating');
            $collection->index('created_at');
        });

        Schema::connection('mongodb')->create('promo_codes', function (Blueprint $collection) {
            $collection->unique('code');
            $collection->index('is_active');
            $collection->index('start_date');
            $collection->index('end_date');
            $collection->index('brands');
        });

        Schema::connection('mongodb')->create('payment_logs', function (Blueprint $collection) {
            $collection->index('order_id');
            $collection->index('payment_id');
            $collection->index('gateway_transaction_id');
            $collection->index('gateway_status');
            $collection->index('reconciled_status');
            $collection->index('received_at');
        });

        Schema::connection('mongodb')->table('promotions', function (Blueprint $collection) {
            $collection->index('is_active');
            $collection->index('product_ids');
            $collection->index('click_target_type');
        });

        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->index('promo_code');
            $collection->index('address_id');
            $collection->index(['status', 'expired_at']);
            $collection->index('expired_at');
        });

        Schema::connection('mongodb')->table('payments', function (Blueprint $collection) {
            $collection->index('payment_method');
        });
    }

    public function down(): void
    {
        foreach (['categories', 'brands', 'addresses', 'reviews', 'promo_codes', 'payment_logs'] as $collection) {
            Schema::connection('mongodb')->dropIfExists($collection);
        }

        // Index tambahan pada promotions/orders/payments sengaja dibiarkan agar
        // rollback tidak mengganggu fitur yang masih memakainya.
    }
};
