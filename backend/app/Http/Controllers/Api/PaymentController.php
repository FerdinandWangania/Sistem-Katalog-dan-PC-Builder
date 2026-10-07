<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\OrderAccess;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use OrderAccess;

    /**
     * Simulasi Pembayaran / Callback Payment Gateway (Midtrans/Xendit).
     * Hanya pemilik pesanan atau admin.
     * POST /api/payments/{orderId}/pay?session_id=... (guest)
     * POST /api/payments/{orderId}/pay
     * Body: {
     *   "gateway_status": "settlement" | "capture" | "paid"
     * }
     */
    public function pay(Request $request, string $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        if (!$this->canAccessOrder($request, $order)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak berhak membayar pesanan ini',
            ], 403);
        }

        $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

        if (!$payment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data pembayaran tidak ditemukan untuk pesanan ini',
            ], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan ini sudah dibayar sebelumnya',
                'data'    => [
                    'order'   => $order,
                    'payment' => $payment,
                ],
            ]);
        }

        // Update payment status
        $payment->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Update order status
        $order->update([
            'status' => 'processing',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pembayaran berhasil dikonfirmasi! Status pesanan kini processing.',
            'data'    => [
                'order'   => $order,
                'payment' => $payment,
            ],
        ]);
    }
}
