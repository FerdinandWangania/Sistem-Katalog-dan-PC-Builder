<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Order — snapshot checkout dari cart.
 *
 * @property string $_id
 * @property string|null $user_id
 * @property string|null $session_id  -- untuk pesanan guest checkout
 * @property string $order_number    -- e.g. "ORD-20261006-0001"
 * @property float  $subtotal
 * @property float  $shipping_fee
 * @property float  $total_amount
 * @property string $status          -- pending | processing | shipped | delivered | cancelled
 * @property string $shipping_address
 * @property \Carbon\Carbon $created_at
 */
class Order extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'orders';

    protected $fillable = [
        'user_id',
        'session_id',
        'order_number',
        'subtotal',
        'shipping_fee',
        'total_amount',
        'status',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'      => 'float',
            'shipping_fee'  => 'float',
            'total_amount'  => 'float',
            'created_at'    => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
