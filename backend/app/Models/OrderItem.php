<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * OrderItem — snapshot item produk saat checkout.
 * price_snapshot menjaga harga historis jika produk berubah harga.
 *
 * @property string $_id
 * @property string $order_id
 * @property string $product_id
 * @property string $source_type -- "catalog" | "builder"
 * @property string|null $build_id -- grup sesi rakitan PC Builder
 * @property string|null $build_name -- nama rakitan saat checkout
 * @property int $qty
 * @property float $price_snapshot
 * @property float|null $hpp_snapshot -- harga pokok saat transaksi
 * @property float|null $price_list_snapshot -- harga dasar sebelum diskon saat transaksi
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
        'build_name',
        'qty',
        'price_snapshot',
        'hpp_snapshot',
        'price_list_snapshot',
    ];

    protected $hidden = [
        'hpp_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price_snapshot' => 'float',
            'hpp_snapshot' => 'float',
            'price_list_snapshot' => 'float',
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
