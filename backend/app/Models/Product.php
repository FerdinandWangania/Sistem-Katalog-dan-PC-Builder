<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Product — katalog produk PC (CPU, GPU, RAM, dll.)
 *
 * @property string $_id
 * @property string $category
 * @property string $brand
 * @property string $name
 * @property float $price
 * @property float $discount_price
 * @property int $stock
 * @property array $specs — embedded object (free-form spec sheet)
 */
class Product extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'products';

    protected $fillable = [
        'category',
        'brand',
        'name',
        'price',
        'discount_price',
        'hpp',
        'stock',
        'image',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'discount_price' => 'float',
            'hpp' => 'float',
            'stock' => 'integer',
            // JANGAN cast 'specs' ke array: cast array Laravel men-json_encode
            // sehingga tersimpan sebagai STRING dan filter DB specs.* (socket,
            // type, form_factor, wattage) tidak pernah match. Tanpa cast,
            // driver menyimpan subdokumen BSON asli; baca tetap array-like.
        ];
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price > 0 ? $this->discount_price : $this->price;
    }
}
