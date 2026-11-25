<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Promotion — promo banner dengan diskon brand atau produk tertentu.
 *
 * @property string $_id
 * @property string $title
 * @property string|null $banner_image
 * @property string|null $click_target_type -- brand | category | product | package | promo | url
 * @property string|null $click_target_value
 * @property array $brands -- list brand yang kena promo
 * @property array $product_ids -- produk terpilih (opsional)
 * @property array $excluded_product_ids -- produk yang dilewati karena diskon manual
 * @property bool $override_manual_discount
 * @property float $discount_percentage
 * @property bool $is_active
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
class Promotion extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'promotions';

    protected $fillable = [
        'title',
        'banner_image',
        'click_target_type',
        'click_target_value',
        'brands',
        'product_ids',
        'excluded_product_ids',
        'override_manual_discount',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'brands' => 'array',
            'product_ids' => 'array',
            'excluded_product_ids' => 'array',
            'override_manual_discount' => 'boolean',
            'discount_percentage' => 'float',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        $now = now();

        return $this->is_active !== false
            && $this->start_date
            && $this->end_date
            && $now->between($this->start_date, $this->end_date);
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', '!=', false)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function products()
    {
        return Product::query()
            ->whereIn('_id', $this->product_ids ?? [])
            ->get();
    }
}
