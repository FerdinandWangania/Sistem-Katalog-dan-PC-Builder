<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Payment — riwayat percobaan pembayaran (1 order bisa punya banyak payment attempt).
 *
 * @property string $_id
 * @property string $order_id
 * @property string $payment_method -- e.g. "bank_transfer", "qris", "credit_card"
 * @property string $gateway_provider -- "midtrans" | "xendit" | "manual"
 * @property string|null $gateway_transaction_id
 * @property float $amount
 * @property string $status -- pending | paid | failed | expired | refunded
 * @property Carbon|null $paid_at
 * @property string|null $snap_token
 * @property string|null $snap_redirect_url
 * @property string|null $va_number
 * @property string|null $qr_string
 */
class Payment extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'payments';

    protected $fillable = [
        'order_id',
        'payment_method',
        'gateway_provider',
        'gateway_transaction_id',
        'amount',
        'status',
        'paid_at',
        'snap_token',
        'snap_redirect_url',
        'va_number',
        'qr_string',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
