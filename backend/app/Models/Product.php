<?php

namespace App\Models;

use App\Services\PromotionService;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Product — katalog produk PC (CPU, GPU, RAM, dll.)
 *
 * @property string $_id
 * @property string $category
 * @property string $brand
 * @property string $name
 * @property string|null $sku
 * @property float $price
 * @property float $discount_price
 * @property float|null $hpp
 * @property int $stock
 * @property string|null $image
 * @property string|null $badge
 * @property float $rating
 * @property int $review_count
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
        'sku',
        'price',
        'discount_price',
        'hpp',
        'stock',
        'image',
        'badge',
        'rating',
        'review_count',
        'specs',
    ];

    protected $appends = [
        'effective_price',
        'discount_percentage',
    ];

    protected $hidden = [
        'hpp',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'discount_price' => 'float',
            'hpp' => 'float',
            'stock' => 'integer',
            'rating' => 'float',
            'review_count' => 'integer',
            'specs' => 'array',
        ];
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    /**
     * Harga efektif: promo aktif menang atas diskon manual, lalu diskon manual.
     */
    public function getEffectivePriceAttribute(): float
    {
        if (array_key_exists('effective_price', $this->attributes)) {
            return (float) $this->attributes['effective_price'];
        }

        return app(PromotionService::class)->effectivePrice($this);
    }

    public function getDiscountPercentageAttribute(): float
    {
        if (array_key_exists('discount_percentage', $this->attributes)) {
            return (float) $this->attributes['discount_percentage'];
        }

        $price = (float) $this->price;
        $effective = $this->effective_price;

        if ($price <= 0 || $effective >= $price) {
            return 0.0;
        }

        return round((($price - $effective) / $price) * 100, 2);
    }
}
