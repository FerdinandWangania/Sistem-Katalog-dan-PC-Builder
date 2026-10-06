<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * OrderItem — snapshot item produk saat checkout.
 * price_snapshot menjaga harga historis jika produk berubah harga.
 *
 * @property string      $_id
 * @property string      $order_id
 * @property string      $product_id
 * @property string      $source_type  -- "catalog" | "builder"
 * @property string|null $build_id     -- grup sesi rakitan PC Builder
 * @property int         $qty
 * @property float       $price_snapshot
 */
class OrderItem extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'source_type',
        'build_id',
        'qty',
        'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'qty'            => 'integer',
            'price_snapshot' => 'float',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
