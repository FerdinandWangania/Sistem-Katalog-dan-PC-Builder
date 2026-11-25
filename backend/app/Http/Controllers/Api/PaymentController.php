<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\OrderAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\OrderStockService;
use App\Services\SnapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class PaymentController extends Controller
{
    use OrderAccess;

    public function __construct(
        private readonly SnapService $snapService,
        private readonly OrderStockService $orderStockService,
    ) {}

    /**
     * Simulasi pembayaran berhasil (demo/test).
     * POST /api/payments/{orderId}/pay
     */
    public function pay(Request $request, string $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (! $order) {
            return $this->notFound('Pesanan tidak ditemukan');
        }

        if (! $this->canAccessOrder($request, $order)) {
            return $this->forbidden('Anda tidak berhak membayar pesanan ini');
        }

        $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

        if (! $payment) {
            return $this->notFound('Data pembayaran tidak ditemukan untuk pesanan ini');
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan ini sudah dibayar sebelumnya',
                'data' => [
                    'order' => $order,
                    'payment' => $payment,
                ],
            ]);
        }

        if (in_array($order->status, ['cancelled', 'delivered'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Pesanan {$order->status} tidak dapat dibayar",
            ], 422);
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_transaction_id' => $payment->gateway_transaction_id ?: 'TRX-'.strtoupper(uniqid()),
        ]);

        if ($order->status === 'pending') {
            $order->update(['status' => 'processing']);
        }

        PaymentLog::create([
            'order_id' => $order->_id,
            'payment_id' => $payment->_id,
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'gateway_method' => $this->methodLabel((string) $payment->payment_method),
            'amount' => (float) $payment->amount,
            'gateway_status' => 'simulated_paid',
            'reconciled_status' => 'paid',
            'received_at' => now(),
            'raw_payload' => ['source' => 'simulation'],
        ]);

        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran berhasil (simulasi)',
            'data' => [
                'order' => $order,
                'payment' => $payment->fresh(),
            ],
        ]);
    }

    /**
     * Minta Snap token Midtrans.
     * POST /api/payments/{orderId}/snap-token
     */
    public function snapToken(Request $request, string $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (! $order) {
            return $this->notFound('Pesanan tidak ditemukan');
        }

        if (! $this->canAccessOrder($request, $order)) {
            return $this->forbidden('Anda tidak berhak membayar pesanan ini');
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan tidak berada pada status pending',
            ], 422);
        }

        if ($order->expired_at && $order->expired_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan sudah kedaluwarsa',
            ], 422);
        }

        $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

        if (! $payment) {
            return $this->notFound('Data pembayaran tidak ditemukan untuk pesanan ini');
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan sudah dibayar',
            ], 422);
        }

        if ($payment->snap_token) {
            return $this->snapResponse($order, $payment);
        }

        try {
            $transaction = $this->snapService->createTransaction($order);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat transaksi Midtrans: '.$exception->getMessage(),
            ], 502);
        }

        $payment->update([
            'snap_token' => $transaction['token'],
            'snap_redirect_url' => $transaction['redirect_url'],
        ]);

        return $this->snapResponse($order, $payment->fresh());
    }

    /**
     * Layar instruksi bayar.
     * GET /api/payments/{orderId}/instructions
     */
    public function instructions(Request $request, string $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (! $order) {
            return $this->notFound('Pesanan tidak ditemukan');
        }

        if (! $this->canAccessOrder($request, $order)) {
            return $this->forbidden('Anda tidak berhak melihat pembayaran ini');
        }

        $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

        if (! $payment) {
            return $this->notFound('Data pembayaran tidak ditemukan untuk pesanan ini');
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'expired_at' => $order->expired_at,
                'payment_status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'va_number' => $payment->va_number,
                'qr_string' => $payment->qr_string,
                'snap_token' => $payment->snap_token,
                'snap_redirect_url' => $payment->snap_redirect_url,
                'instruction' => $this->instructionText($payment),
            ],
        ]);
    }

    /**
     * Webhook Midtrans. Wajib signature SHA512 yang valid.
     * POST /api/payments/callback
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderNumber = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($orderNumber === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
            return $this->forbidden('Payload callback tidak lengkap');
        }

        $expected = $this->snapService->signature($orderNumber, $statusCode, $grossAmount);

        if (! hash_equals($expected, $signature)) {
            return $this->forbidden('Signature callback tidak valid');
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Pesanan tidak ditemukan',
            ]);
        }

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? '');
        $gatewayTransactionId = (string) ($payload['transaction_id'] ?? '');
        $gatewayMethod = (string) ($payload['payment_type'] ?? '');
        $amount = (float) $grossAmount;
        $reconciledStatus = $this->reconcileStatus($transactionStatus, $fraudStatus);
        $amountMatches = abs($amount - (float) $order->total_amount) <= 0.01;

        $payment = Payment::where('order_id', $order->_id)->orderBy('created_at', 'desc')->first();

        if (! $amountMatches) {
            PaymentLog::create([
                'order_id' => $order->_id,
                'payment_id' => $payment?->_id,
                'gateway_transaction_id' => $gatewayTransactionId,
                'gateway_method' => $gatewayMethod,
                'amount' => $amount,
                'gateway_status' => $transactionStatus,
                'reconciled_status' => 'pending',
                'received_at' => now(),
                'raw_payload' => $payload,
            ]);

            return response()->json([
                'status' => 'ignored',
                'message' => 'Nominal callback tidak cocok dengan total pesanan',
            ]);
        }

        if (! $payment) {
            $payment = Payment::create([
                'order_id' => $order->_id,
                'payment_method' => $this->mapPaymentMethod($gatewayMethod, (string) $order->payment_method),
                'gateway_provider' => 'midtrans',
                'gateway_transaction_id' => $gatewayTransactionId !== '' ? $gatewayTransactionId : 'TRX-'.strtoupper(uniqid()),
                'amount' => $amount,
                'status' => 'pending',
                'paid_at' => null,
            ]);
        }

        PaymentLog::create([
            'order_id' => $order->_id,
            'payment_id' => $payment->_id,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_method' => $gatewayMethod,
            'amount' => $amount,
            'gateway_status' => $transactionStatus,
            'reconciled_status' => $reconciledStatus,
            'received_at' => now(),
            'raw_payload' => $payload,
        ]);

        $paymentUpdates = [
            'status' => $reconciledStatus,
            'gateway_transaction_id' => $gatewayTransactionId !== '' ? $gatewayTransactionId : $payment->gateway_transaction_id,
            'payment_method' => $this->mapPaymentMethod($gatewayMethod, (string) $payment->payment_method),
        ];

        $vaNumber = $this->extractVaNumber($payload);

        if ($vaNumber !== null) {
            $paymentUpdates['va_number'] = $vaNumber;
        }

        if (! empty($payload['qr_string'])) {
            $paymentUpdates['qr_string'] = (string) $payload['qr_string'];
        }

        if ($reconciledStatus === 'paid') {
            $paymentUpdates['paid_at'] = $payment->paid_at ?? now();
        }

        if (in_array($payment->status, ['paid', 'refunded'], true) && in_array($reconciledStatus, ['pending', 'failed', 'expired'], true)) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Pembayaran sudah final; callback tidak menurunkan status',
            ]);
        }

        $payment->update($paymentUpdates);

        if ($reconciledStatus === 'paid') {
            if ($order->status === 'pending') {
                $order->update(['status' => 'processing']);
            }
        } elseif (in_array($reconciledStatus, ['expired', 'failed'], true)) {
            if (! $order->isFinal()) {
                $order->update(['status' => 'cancelled']);
                $this->orderStockService->restore($order);
            }
        } elseif ($reconciledStatus === 'refunded') {
            $this->orderStockService->restore($order);
        }

        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'message' => 'Callback diproses',
            'data' => [
                'order' => $order,
                'payment' => $payment->fresh(),
            ],
        ]);
    }

    /**
     * Daftar log callback pembayaran (admin/keuangan).
     * GET /api/admin/payment-logs
     */
    public function logs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'method' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = PaymentLog::query();

        if (isset($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }

        if (isset($validated['status'])) {
            $query->where('reconciled_status', $validated['status']);
        }

        if (isset($validated['method'])) {
            $query->where('gateway_method', 'regex', '/'.preg_quote($validated['method'], '/').'/i');
        }

        if (isset($validated['from'])) {
            $query->where('received_at', '>=', Carbon::parse($validated['from'])->startOfDay());
        }

        if (isset($validated['to'])) {
            $query->where('received_at', '<=', Carbon::parse($validated['to'])->endOfDay());
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $total = (clone $query)->count();

        $logs = $query->orderBy('received_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $orders = Order::whereIn('_id', $logs->pluck('order_id')->filter()->unique()->values()->all())
            ->get(['order_number', 'total_amount', 'status'])
            ->mapWithKeys(static fn (Order $order): array => [
                (string) $order->_id => [
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                ],
            ]);

        foreach ($logs as $log) {
            $log->setAttribute('order', $orders[(string) $log->order_id] ?? null);
        }

        return response()->json([
            'status' => 'success',
            'data' => $logs,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Detail log + seluruh attempt pembayaran pesanan.
     * GET /api/admin/payment-logs/{id}
     */
    public function logDetail(string $id): JsonResponse
    {
        $log = PaymentLog::find($id);

        if (! $log) {
            return $this->notFound('Log pembayaran tidak ditemukan');
        }

        $order = Order::find($log->order_id);
        $payments = $order ? Payment::where('order_id', $order->_id)->orderBy('created_at')->get() : collect();

        return response()->json([
            'status' => 'success',
            'data' => [
                'log' => $log,
                'order' => $order,
                'payment_attempts' => $payments,
            ],
        ]);
    }

    private function snapResponse(Order $order, Payment $payment): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'snap_token' => $payment->snap_token,
                'snap_redirect_url' => $payment->snap_redirect_url,
                'client_key' => $this->snapService->clientKey(),
                'snap_script_url' => $this->snapService->snapScriptUrl(),
                'is_production' => (bool) config('midtrans.is_production', false),
            ],
        ]);
    }

    private function reconcileStatus(string $transactionStatus, string $fraudStatus): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };
    }

    private function mapPaymentMethod(string $gatewayMethod, string $fallback): string
    {
        return match ($gatewayMethod) {
            'qris' => 'qris',
            'credit_card' => 'credit_card',
            'bank_transfer', 'echannel', 'cstore' => 'bank_transfer',
            default => $fallback !== '' ? $fallback : 'bank_transfer',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractVaNumber(array $payload): ?string
    {
        if (! empty($payload['va_numbers'][0]['va_number'])) {
            return (string) $payload['va_numbers'][0]['va_number'];
        }

        foreach (['permata_va_number', 'bca_va_number', 'bni_va_number', 'bri_va_number'] as $key) {
            if (! empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'VA',
            'qris' => 'QRIS',
            'credit_card' => 'Kartu Kredit',
            default => $method,
        };
    }

    private function instructionText(Payment $payment): string
    {
        if ($payment->status === 'paid') {
            return 'Pembayaran sudah diterima. Pesanan sedang diproses.';
        }

        if ($payment->status === 'expired') {
            return 'Pembayaran sudah kedaluwarsa. Silakan buat pesanan baru.';
        }

        return match ((string) $payment->payment_method) {
            'bank_transfer' => $payment->va_number
                ? 'Transfer ke nomor Virtual Account '.$payment->va_number.' sesuai nominal.'
                : 'Selesaikan transfer melalui halaman pembayaran Midtrans.',
            'qris' => $payment->qr_string
                ? 'Pindai kode QRIS yang tampil di halaman pembayaran.'
                : 'Buka halaman pembayaran Midtrans untuk menampilkan QRIS.',
            'credit_card' => 'Selesaikan pembayaran kartu kredit pada halaman Midtrans.',
            default => 'Ikuti instruksi pembayaran pada halaman Midtrans.',
        };
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 404);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 403);
    }
}
