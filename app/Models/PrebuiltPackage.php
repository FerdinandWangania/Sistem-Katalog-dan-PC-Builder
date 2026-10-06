<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * PrebuiltPackage — paket rakitan PC siap beli (preset).
 *
 * @property string $_id
 * @property string $name
 * @property string $tag         -- e.g. "gaming", "office", "workstation"
 * @property float  $total_price
 */
class PrebuiltPackage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'prebuilt_packages';

    protected $fillable = [
        'name',
        'tag',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'float',
        ];
    }

    public function items()
    {
        return $this->hasMany(PackageItem::class, 'package_id');
    }
}
