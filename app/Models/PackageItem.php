<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * PackageItem — komponen produk dalam sebuah prebuilt package.
 *
 * @property string $_id
 * @property string $package_id
 * @property string $product_id
 */
class PackageItem extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'package_items';

    protected $fillable = [
        'package_id',
        'product_id',
    ];

    public function package()
    {
        return $this->belongsTo(PrebuiltPackage::class, 'package_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
