<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Review — ulasan produk, satu per pengguna per produk.
 *
 * @property string $_id
 * @property string $product_id
 * @property string $user_id
 * @property string $user_name
 * @property int $rating
 * @property string $comment
 */
class Review extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'user_name',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
