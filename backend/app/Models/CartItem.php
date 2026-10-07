<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * CartItem — satu baris dalam keranjang belanja.
 *
 * @property string      $_id
 * @property string      $cart_id
 * @property string      $product_id
 * @property string      $source_type  -- "catalog" | "builder"
 * @property string|null $build_id     -- grup sesi rakitan PC Builder
 * @property int         $qty
 * @property float       $price_snapshot
 */
class CartItem extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'cart_items';

    protected $fillable = [
        'cart_id',
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

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
