<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * PromoCode — kode promo persentase dengan batas brand dan minimum belanja.
 *
 * @property string $_id
 * @property string $code
 * @property float $discount_percentage
 * @property array|null $brands
 * @property float|null $min_subtotal
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 */
class PromoCode extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'promo_codes';

    protected $fillable = [
        'code',
        'discount_percentage',
        'brands',
        'min_subtotal',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'float',
            'brands' => 'array',
            'min_subtotal' => 'float',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function isActive(): bool
    {
        return $this->is_active
            && $this->start_date
            && $this->end_date
            && now()->between($this->start_date, $this->end_date);
    }
}
