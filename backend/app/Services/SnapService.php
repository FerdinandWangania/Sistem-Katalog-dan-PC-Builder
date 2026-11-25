<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien Midtrans Snap (sandbox/production).
 */
class SnapService
{
    /**
     * @return array{token: string, redirect_url: string}
     */
    public function createTransaction(Order $order): array
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi');
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->post($this->snapUrl(), $this->payload($order));

        if (! $response->successful()) {
            $message = $response->json('error_messages.0')
                ?? $response->json('status_message')
                ?? 'Midtrans menolak permintaan pembayaran';

            throw new RuntimeException($message);
        }

        $token = (string) $response->json('token');
        $redirectUrl = (string) $response->json('redirect_url');

        if ($token === '') {
            throw new RuntimeException('Midtrans tidak mengembalikan snap token');
        }

        return [
            'token' => $token,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function signature(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash(
            'sha512',
            $orderId.$statusCode.$grossAmount.(string) config('midtrans.server_key'),
        );
    }

    public function clientKey(): ?string
    {
        $clientKey = config('midtrans.client_key');

        return $clientKey !== null && $clientKey !== '' ? (string) $clientKey : null;
    }

    public function snapScriptUrl(): string
    {
        $configured = config('midtrans.snap_script_url');

        if ($configured !== null && $configured !== '') {
            return (string) $configured;
        }

        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order): array
    {
        $order->loadMissing(['items.product', 'user']);

        $itemDetails = [];
        $itemsTotal = 0.0;

        foreach ($order->items as $item) {
            $name = $item->product?->name ?? $item->build_name ?? 'Produk';
            $price = (int) round((float) $item->price_snapshot);
            $quantity = (int) $item->qty;

            $itemDetails[] = [
                'id' => (string) $item->product_id,
                'price' => $price,
                'quantity' => $quantity,
                'name' => mb_substr((string) $name, 0, 50),
            ];

            $itemsTotal += $price * $quantity;
        }

        if ((float) $order->shipping_fee > 0) {
            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => (int) round((float) $order->shipping_fee),
                'quantity' => 1,
                'name' => 'Ongkos Kirim',
            ];
            $itemsTotal += (float) $order->shipping_fee;
        }

        if ((float) $order->service_fee > 0) {
            $itemDetails[] = [
                'id' => 'SERVICE-FEE',
                'price' => (int) round((float) $order->service_fee),
                'quantity' => 1,
                'name' => 'Biaya Layanan',
            ];
            $itemsTotal += (float) $order->service_fee;
        }

        $grossAmount = (int) round((float) $order->total_amount);
        $rounding = $grossAmount - (int) round($itemsTotal);

        if ($rounding !== 0) {
            $itemDetails[] = [
                'id' => 'ADJUSTMENT',
                'price' => $rounding,
                'quantity' => 1,
                'name' => 'Penyesuaian',
            ];
        }

        $name = (string) ($order->user?->name ?: 'Pembeli');
        $email = (string) ($order->user?->email ?: '');
        $phone = (string) ($order->user?->phone ?: '');

        $payload = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemDetails,
            'customer_details' => array_filter([
                'first_name' => mb_substr($name, 0, 50),
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'enabled_payments' => $this->enabledPayments((string) $order->payment_method),
        ];

        $finishUrl = config('midtrans.finish_url');

        if ($finishUrl !== null && $finishUrl !== '') {
            $payload['callbacks'] = ['finish' => (string) $finishUrl];
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function enabledPayments(string $paymentMethod): array
    {
        return match ($paymentMethod) {
            'qris' => ['qris'],
            'credit_card' => ['credit_card'],
            default => ['bank_transfer'],
        };
    }

    private function snapUrl(): string
    {
        $configured = config('midtrans.snap_url');

        if ($configured !== null && $configured !== '') {
            return (string) $configured;
        }

        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function isProduction(): bool
    {
        return (bool) config('midtrans.is_production', false);
    }
}
