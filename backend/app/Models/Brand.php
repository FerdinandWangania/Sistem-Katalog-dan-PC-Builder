<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Brand — daftar produsen unik.
 *
 * @property string $_id
 * @property string $name
 * @property string|null $logo
 * @property bool $is_active
 */
class Brand extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'brands';

    protected $fillable = [
        'name',
        'logo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
