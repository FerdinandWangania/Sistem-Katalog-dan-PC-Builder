<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\OrderAccess;
use App\Http\Controllers\Api\Concerns\ResolvesIdentity;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\CompatibilityService;
use App\Services\OrderStockService;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;
use RuntimeException;

class OrderController extends Controller
{
    use OrderAccess;
    use ResolvesIdentity;

    public function __construct(
        private readonly CompatibilityService $compatibility,
        private readonly PromoCodeService $promoCodeService,
        private readonly OrderStockService $orderStockService,
    ) {}

    /**
     * Checkout keranjang menjadi pesanan pending + payment pending.
     * POST /api/orders/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string'],
            'address_id' => ['sometimes', 'nullable', 'string'],
            'shipping_address' => ['nullable', 'string', 'max:1000', 'required_without:address_id'],
            'shipping_method' => ['sometimes', 'string', 'in:courier,pickup'],
            'payment_method' => ['sometimes', 'string', 'in:bank_transfer,qris,credit_card'],
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gateway_provider' => ['sometimes', 'string', 'in:midtrans,xendit,bca_va,mandiri_va'],
        ]);

        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->checkoutError('user_id atau session_id wajib diisi', 422);
        }

        $user = $identity['user'];

        if ($user && ! $user->email_verified_at) {
            return $this->checkoutError(
                'Verifikasi email dulu sebelum checkout (POST /api/resend-verification untuk token baru)',
                403,
            );
        }

        $sessionId = trim((string) $request->input('session_id', ''));

        if ($user && $sessionId !== '') {
            $this->mergeGuestCart((string) $user->_id, $sessionId);
        }

        $cart = $this->findCart($identity);

        if (! $cart) {
            return $this->checkoutError('Keranjang belanja kosong', 400);
        }

        $cart->loadMissing('items.product');
        $cartItems = $cart->items;

        if ($cartItems->isEmpty()) {
            return $this->checkoutError('Tidak ada item dalam keranjang untuk dicheckout', 400);
        }

        $productMap = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (! $product) {
                return $this->checkoutError('Terdapat produk yang sudah tidak tersedia, silakan perbarui keranjang', 422);
            }

            if ((int) $product->stock < (int) $item->qty) {
                return $this->checkoutError(
                    "Stok {$product->name} tidak mencukupi (tersedia: {$product->stock}, diminta: {$item->qty}).",
                    422,
                );
            }

            $currentPrice = (float) $product->effective_price;

            if ((float) $item->price_snapshot !== $currentPrice) {
                $item->price_snapshot = $currentPrice;
                $item->save();
            }

            $productMap[(string) $item->_id] = $product;
        }

        $buildFailures = [];

        foreach ($cartItems->where('source_type', 'builder')->whereNotNull('build_id')->groupBy('build_id') as $buildId => $buildItems) {
            $analysis = $this->compatibility->analyzeCartItems($buildItems, [
                'build_name' => $buildItems->first()?->build_name,
            ]);

            if (! $analysis['is_compatible']) {
                $buildFailures[] = [
                    'build_id' => $buildId,
                    'build_name' => $analysis['build_name'],
                    'errors' => $analysis['errors'],
                ];
            }
        }

        if ($buildFailures !== []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rakitan pada keranjang tidak lagi kompatibel. Perbaiki rakitan sebelum checkout.',
                'builds' => $buildFailures,
            ], 422);
        }

        $addressId = $validated['address_id'] ?? null;
        $shippingAddress = trim((string) ($validated['shipping_address'] ?? ''));

        if ($addressId !== null && $addressId !== '') {
            $address = Address::where('_id', $addressId)
                ->where($this->identityQuery($identity))
                ->first();

            if (! $address) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Alamat tidak ditemukan',
                    'errors' => ['address_id' => ['Alamat tidak ditemukan atau bukan milik Anda']],
                ], 422);
            }

            $shippingAddress = trim(sprintf(
                '%s - %s - %s, %s',
                $address->recipient,
                $address->phone,
                $address->address,
                $address->city,
            ));
        }

        if ($shippingAddress === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Alamat pengiriman wajib diisi',
                'errors' => ['shipping_address' => ['Alamat pengiriman wajib diisi']],
            ], 422);
        }

        $promoCode = null;
        $promoResult = null;
        $promoDiscounts = [];

        if (! empty($validated['promo_code'])) {
            $promoCode = PromoCode::where('code', strtoupper(trim($validated['promo_code'])))->first();

            if (! $promoCode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kode promo tidak ditemukan',
                    'errors' => ['promo_code' => ['Kode promo tidak ditemukan']],
                ], 422);
            }

            if (! $promoCode->isActive()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kode promo tidak aktif atau di luar masa berlaku',
                    'errors' => ['promo_code' => ['Kode promo tidak aktif atau di luar masa berlaku']],
                ], 422);
            }

            $promoResult = $this->promoCodeService->validateForCart($promoCode, $cart);

            if (! $promoResult['valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $promoResult['message'],
                    'errors' => ['promo_code' => [$promoResult['message']]],
                ], 422);
            }

            foreach ($promoResult['items'] as $line) {
                $promoDiscounts[(string) $line['cart_item_id']] = (float) $line['discount_amount'];
            }
        }

        $orderItems = [];
        $subtotalBeforePromo = 0.0;
        $subtotal = 0.0;

        foreach ($cartItems as $item) {
            $product = $productMap[(string) $item->_id];

            if (! $product) {
                return $this->checkoutError('Terdapat produk yang sudah tidak tersedia, silakan perbarui keranjang', 422);
            }

            $qty = (int) $item->qty;
            $unitPrice = (float) $product->effective_price;
            $lineTotal = round($unitPrice * $qty, 2);
            $lineDiscount = $promoDiscounts[(string) $item->_id] ?? 0.0;
            $discountedLineTotal = max(0.0, round($lineTotal - $lineDiscount, 2));
            $discountedUnitPrice = $qty > 0 ? round($discountedLineTotal / $qty, 2) : $discountedLineTotal;

            $subtotalBeforePromo += $lineTotal;
            $subtotal += round($discountedUnitPrice * $qty, 2);

            $orderItems[] = [
                'product_id' => (string) $item->product_id,
                'source_type' => $item->source_type,
                'build_id' => $item->build_id,
                'build_name' => $item->build_name,
                'qty' => $qty,
                'price_snapshot' => $discountedUnitPrice,
                'hpp_snapshot' => $product->hpp,
                'price_list_snapshot' => (float) $product->price,
            ];
        }

        $subtotalBeforePromo = round($subtotalBeforePromo, 2);
        $subtotal = round($subtotal, 2);
        $discountAmount = round(max(0, $subtotalBeforePromo - $subtotal), 2);

        $shippingMethod = $validated['shipping_method'] ?? 'courier';
        $shippingFee = $shippingMethod === 'pickup' ? 0.0 : (float) config('orders.shipping_fee');
        $paymentMethod = $validated['payment_method'] ?? 'bank_transfer';

        $merchandiseTotal = round($subtotal + $shippingFee, 2);

        if ($paymentMethod === 'qris' && $merchandiseTotal > (float) config('orders.qris_max_amount')) {
            return response()->json([
                'status' => 'error',
                'message' => 'QRIS hanya dapat dipakai untuk transaksi maksimal Rp '.
                    number_format((float) config('orders.qris_max_amount'), 0, ',', '.'),
                'errors' => ['payment_method' => ['QRIS melebihi batas transaksi']],
            ], 422);
        }

        $serviceFee = 0.0;

        if ($paymentMethod === 'credit_card') {
            $serviceFee = round(
                $merchandiseTotal * ((float) config('orders.credit_card_service_fee_percent') / 100),
                2,
            );
        }

        $totalAmount = round($subtotal + $shippingFee + $serviceFee, 2);
        $order = null;
        $payment = null;
        $decremented = [];

        try {
            $order = Order::create([
                'user_id' => $user?->_id,
                'session_id' => $user ? null : $identity['session_id'],
                'order_number' => $this->generateOrderNumber(),
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'service_fee' => $serviceFee,
                'discount_amount' => $discountAmount,
                'promo_code' => $promoCode?->code,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'shipping_address' => $shippingAddress,
                'address_id' => $addressId ?: null,
                'shipping_method' => $shippingMethod,
                'payment_method' => $paymentMethod,
                'expired_at' => now()->addHours((int) config('orders.payment_ttl_hours')),
                'stock_restored' => false,
            ]);

            foreach ($orderItems as $orderItem) {
                OrderItem::create([
                    ...$orderItem,
                    'order_id' => $order->_id,
                ]);
            }

            foreach ($cartItems as $item) {
                $qty = (int) $item->qty;
                $updated = Product::where('_id', $item->product_id)
                    ->where('stock', '>=', $qty)
                    ->decrement('stock', $qty);

                if (! $updated) {
                    throw new RuntimeException("Stok {$item->product?->name} berubah saat checkout. Silakan coba lagi.");
                }

                $decremented[] = [(string) $item->product_id, $qty];
            }

            $payment = Payment::create([
                'order_id' => $order->_id,
                'payment_method' => $paymentMethod,
                'gateway_provider' => $validated['gateway_provider'] ?? 'midtrans',
                'gateway_transaction_id' => 'TRX-'.strtoupper((string) new ObjectId),
                'amount' => $totalAmount,
                'status' => 'pending',
                'paid_at' => null,
            ]);

            CartItem::where('cart_id', $cart->_id)->delete();
            $cart->total_price = 0;
            $cart->save();
        } catch (\Throwable $exception) {
            foreach ($decremented as [$productId, $qty]) {
                Product::where('_id', $productId)->increment('stock', $qty);
            }

            if ($order) {
                OrderItem::where('order_id', $order->_id)->delete();
                Payment::where('order_id', $order->_id)->delete();
                $order->delete();
            }

            return $this->checkoutError($exception->getMessage(), 422);
        }

        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout berhasil! Pesanan dibuat.',
            'data' => [
                'order' => $order,
                'payment' => $payment,
            ],
        ], 201);
    }

    /**
     * Update status pesanan: processing | shipped | delivered | cancelled.
     * PATCH /api/orders/{id}/status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:processing,shipped,delivered,cancelled'],
        ]);

        $order = Order::find($id);

        if (! $order) {
            return $this->notFound();
        }

        if ($order->isFinal()) {
            return response()->json([
                'status' => 'error',
                'message' => "Pesanan {$order->status} tidak dapat diubah lagi",
            ], 422);
        }

        $previous = $order->status;
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'cancelled' && in_array($previous, ['pending', 'processing'], true)) {
            $this->orderStockService->restore($order);
            Payment::where('order_id', $order->_id)->where('status', 'pending')->update(['status' => 'expired']);
        }

        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'message' => "Status pesanan diubah menjadi {$validated['status']}",
            'data' => $order,
        ]);
    }

    /**
     * List pesanan milik sendiri; admin/keuangan dapat melihat semua.
     * GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        [$user, $sessionId] = $this->accessIdentity($request);

        $query = Order::with(['items.product', 'payments']);

        if ($user && $user->canReadOperational()) {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
        } elseif ($user) {
            $query->where('user_id', (string) $user->_id);
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Wajib login atau sertakan session_id',
            ], 401);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'count' => $orders->count(),
            'data' => $orders,
        ]);
    }

    /**
     * Detail pesanan (pemilik, admin, atau keuangan).
     * GET /api/orders/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'payments', 'user'])->find($id);

        if (! $order) {
            return $this->notFound();
        }

        [$reader] = $this->accessIdentity($request);

        if (! $this->canAccessOrder($request, $order)
            && ! ($reader && $reader->canReadOperational())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak berhak melihat pesanan ini',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * Batalkan pesanan sendiri (hanya saat masih pending).
     * POST /api/orders/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return $this->notFound();
        }

        if (! $this->canAccessOrder($request, $order)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak berhak membatalkan pesanan ini',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => "Pesanan {$order->status} tidak bisa dibatalkan sendiri, hubungi admin",
            ], 422);
        }

        $order->update(['status' => 'cancelled']);
        $this->orderStockService->restore($order);
        Payment::where('order_id', $order->_id)->where('status', 'pending')->update(['status' => 'expired']);
        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'message' => 'Pesanan dibatalkan, stok dikembalikan',
            'data' => $order,
        ]);
    }

    /**
     * Pindahkan item cart guest ke cart user saat login/checkout.
     */
    private function mergeGuestCart(string $userId, string $sessionId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->first();

        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $userId], ['total_price' => 0]);

        if ((string) $guestCart->_id === (string) $userCart->_id) {
            return;
        }

        $guestItems = CartItem::where('cart_id', $guestCart->_id)->get();

        foreach ($guestItems as $item) {
            $existing = CartItem::where('cart_id', $userCart->_id)
                ->where('product_id', $item->product_id)
                ->where('source_type', $item->source_type)
                ->where('build_id', $item->build_id)
                ->first();

            if ($existing && $item->source_type === 'catalog') {
                $existing->qty += $item->qty;
                $existing->save();
                $item->delete();
            } else {
                $item->cart_id = $userCart->_id;
                $item->save();
            }
        }

        $userCart->recalculateTotal();
        $guestCart->delete();
    }

    /**
     * @param  array{user_id: string|null, session_id: string|null, user: User|null}  $identity
     */
    private function findCart(array $identity): ?Cart
    {
        return Cart::where($this->identityQuery($identity))->first();
    }

    private function generateOrderNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $number = 'ORD-'.now()->format('Ymd').'-'.rand(1000, 9999);

            if (! Order::where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return 'ORD-'.now()->format('YmdHis').'-'.strtoupper(substr((string) new ObjectId, -6));
    }

    private function checkoutError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Pesanan tidak ditemukan',
        ], 404);
    }
}
