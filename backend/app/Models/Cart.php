<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Cart — keranjang belanja aktif milik user (max 1 per user).
 * Mendukung guest cart via session_id (user_id nullable).
 *
 * @property string      $_id
 * @property string|null $user_id
 * @property string|null $session_id  -- untuk guest cart
 * @property float       $total_price
 * @property \Carbon\Carbon $updated_at
 */
class Cart extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'carts';

    protected $fillable = [
        'user_id',
        'session_id',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'float',
            'updated_at'  => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function recalculateTotal(): void
    {
        $this->total_price = (float) $this->items()->get()
            ->sum(fn ($i) => $i->price_snapshot * $i->qty);
        $this->save();
    }
}
