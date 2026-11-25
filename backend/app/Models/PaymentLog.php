<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * PaymentLog — log mentah callback gateway untuk rekonsiliasi.
 *
 * @property string $_id
 * @property string $order_id
 * @property string|null $payment_id
 * @property string|null $gateway_transaction_id
 * @property string|null $gateway_method
 * @property float $amount
 * @property string $gateway_status
 * @property string $reconciled_status
 * @property Carbon $received_at
 * @property array $raw_payload
 */
class PaymentLog extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'payment_logs';

    protected $fillable = [
        'order_id',
        'payment_id',
        'gateway_transaction_id',
        'gateway_method',
        'amount',
        'gateway_status',
        'reconciled_status',
        'received_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'received_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
