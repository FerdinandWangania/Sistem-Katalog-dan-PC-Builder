<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Order — snapshot checkout dari cart.
 *
 * @property string $_id
 * @property string|null $user_id
 * @property string|null $session_id -- untuk pesanan guest checkout
 * @property string $order_number -- e.g. "ORD-20261006-0001"
 * @property float $subtotal
 * @property float $shipping_fee
 * @property float $service_fee
 * @property float $discount_amount
 * @property string|null $promo_code
 * @property float $total_amount
 * @property string $status -- pending | processing | shipped | delivered | cancelled
 * @property string $shipping_address
 * @property string|null $address_id
 * @property string $shipping_method -- courier | pickup
 * @property string $payment_method -- bank_transfer | qris | credit_card
 * @property Carbon|null $expired_at
 * @property bool $stock_restored
 * @property Carbon $created_at
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
        'service_fee',
        'discount_amount',
        'promo_code',
        'total_amount',
        'status',
        'shipping_address',
        'address_id',
        'shipping_method',
        'payment_method',
        'expired_at',
        'stock_restored',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'shipping_fee' => 'float',
            'service_fee' => 'float',
            'discount_amount' => 'float',
            'total_amount' => 'float',
            'expired_at' => 'datetime',
            'stock_restored' => 'boolean',
            'created_at' => 'datetime',
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

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class, 'order_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled'], true);
    }
}
