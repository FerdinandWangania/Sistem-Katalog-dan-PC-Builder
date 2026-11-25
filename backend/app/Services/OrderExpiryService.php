<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentLog;

/**
 * Job kedaluwarsa pesanan pending: batalkan, kembalikan stok, tandai payment.
 */
class OrderExpiryService
{
    public function __construct(private readonly OrderStockService $orderStockService) {}

    public function expirePending(): int
    {
        $orders = Order::where('status', 'pending')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->get();

        $expired = 0;

        foreach ($orders as $order) {
            $paidPayment = Payment::where('order_id', $order->_id)
                ->where('status', 'paid')
                ->first();

            if ($paidPayment) {
                $order->update(['status' => 'processing']);

                continue;
            }

            $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

            Payment::where('order_id', $order->_id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            $order->update(['status' => 'cancelled']);
            $this->orderStockService->restore($order);

            PaymentLog::create([
                'order_id' => $order->_id,
                'payment_id' => $payment?->_id,
                'gateway_transaction_id' => $payment?->gateway_transaction_id,
                'gateway_method' => 'system',
                'amount' => (float) $order->total_amount,
                'gateway_status' => 'system_expired',
                'reconciled_status' => 'expired',
                'received_at' => now(),
                'raw_payload' => ['source' => 'orders:expire'],
            ]);

            $expired++;
        }

        return $expired;
    }
}
