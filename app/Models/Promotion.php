<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Promotion — promo diskon berlaku pada brand tertentu dalam rentang waktu.
 *
 * @property string   $_id
 * @property string   $title
 * @property array    $brands            -- list brand yang kena promo
 * @property float    $discount_percentage
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 */
class Promotion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'promotions';

    protected $fillable = [
        'title',
        'brands',
        'discount_percentage',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'brands'               => 'array',
            'discount_percentage'  => 'float',
            'start_date'           => 'datetime',
            'end_date'             => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        $now = now();
        return $now->between($this->start_date, $this->end_date);
    }
}
