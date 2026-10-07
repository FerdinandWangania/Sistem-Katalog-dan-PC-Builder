<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\OrderAccess;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class OrderController extends Controller
{
    use OrderAccess;
    /**
     * Checkout keranjang belanja menjadi Pesanan.
     * Mendukung user terdaftar (user_id) maupun guest (session_id).
     * Body: {
     *   "user_id": "...",             // salah satu dari user_id / session_id wajib diisi
     *   "session_id": "...",
     *   "shipping_address": "Jl. Sudirman No. 12, Jakarta",
     *   "shipping_fee": 25000,
     *   "gateway_provider": "midtrans"
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => 'nullable|string',
            'session_id'       => 'nullable|string',
            'shipping_address' => 'required|string',
            'shipping_fee'     => 'nullable|numeric|min:0',
            'gateway_provider' => 'nullable|string|in:midtrans,xendit,bca_va,mandiri_va',
        ]);

        $userId = $validated['user_id'] ?? null;
        $sessionId = $validated['session_id'] ?? null;

        $user = null;
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User tidak ditemukan',
                ], 404);
            }
        }

        if (!$userId && !$sessionId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'user_id atau session_id wajib diisi',
            ], 422);
        }

        // User terdaftar wajib verifikasi email dulu (guest bebas)
        if ($user && !$user->email_verified_at) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Verifikasi email dulu sebelum checkout (POST /api/resend-verification untuk token baru)',
            ], 403);
        }

        // Gabungkan cart guest ke cart user bila keduanya dikirim
        if ($userId && $sessionId) {
            $this->mergeGuestCart($userId, $sessionId);
        }

        $cart = $userId
            ? Cart::where('user_id', $userId)->first()
            : Cart::where('session_id', $sessionId)->first();
        if (!$cart) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Keranjang belanja kosong',
            ], 400);
        }

        $cartItems = CartItem::where('cart_id', $cart->_id)->get();
        if ($cartItems->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada item dalam keranjang untuk dicheckout',
            ], 400);
        }

        // Revalidasi harga & stok dari data produk terkini (snapshot cart bisa basi)
        foreach ($cartItems as $item) {
            $product = \App\Models\Product::find($item->product_id);
            if (!$product) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Terdapat produk yang sudah tidak tersedia, silakan perbarui keranjang',
                ], 422);
            }
            if ($product->stock < $item->qty) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Stok {$product->name} tidak mencukupi (tersedia: {$product->stock}, diminta: {$item->qty}).",
                ], 422);
            }
            if ($item->price_snapshot != $product->effective_price) {
                $item->price_snapshot = $product->effective_price;
                $item->save();
            }
        }

        $shippingFee = (float) ($validated['shipping_fee'] ?? 25000);
        $subtotal = (float) $cartItems->sum(fn ($i) => $i->price_snapshot * $i->qty);
        $totalAmount = $subtotal + $shippingFee;

        // 1. Buat Order (nomor unik dengan retry)
        $order = Order::create([
            'user_id'          => $user?->_id,
            'session_id'       => $user ? null : $sessionId,
            'order_number'     => $this->generateOrderNumber(),
            'subtotal'         => $subtotal,
            'shipping_fee'     => $shippingFee,
            'total_amount'     => $totalAmount,
            'status'           => 'pending',
            'shipping_address' => $validated['shipping_address'],
        ]);

        // 2. Transfer CartItems ke OrderItems (Snapshot)
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id'       => $order->_id,
                'product_id'     => $item->product_id,
                'source_type'    => $item->source_type,
                'build_id'       => $item->build_id,
                'qty'            => $item->qty,
                'price_snapshot' => $item->price_snapshot,
            ]);
        }

        // 3. Kurangi stok produk
        foreach ($cartItems as $item) {
            \App\Models\Product::where('_id', $item->product_id)->decrement('stock', $item->qty);
        }

        // 4. Buat Payment pending
        $gatewayProvider = $validated['gateway_provider'] ?? 'midtrans';
        $payment = Payment::create([
            'order_id'               => $order->_id,
            'gateway_provider'       => $gatewayProvider,
            'gateway_transaction_id' => 'TRX-' . strtoupper((string) new ObjectId()),
            'status'                 => 'pending',
            'amount'                 => $totalAmount,
            'paid_at'                => null,
        ]);

        // 5. Kosongkan keranjang
        CartItem::where('cart_id', $cart->_id)->delete();
        $cart->total_price = 0;
        $cart->save();

        // 6. Load relasi untuk respon
        $order->load(['items.product', 'payments']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Checkout berhasil! Pesanan dibuat.',
            'data'    => [
                'order'   => $order,
                'payment' => $payment,
            ],
        ], 201);
    }

    /**
     * Update status pesanan: processing | shipped | delivered | cancelled.
     * Cancel mengembalikan stok. Status final (delivered/cancelled) tidak bisa diubah.
     * PATCH /api/orders/{id}/status  Body: { "status": "..." }
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:processing,shipped,delivered,cancelled',
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Pesanan {$order->status} tidak dapat diubah lagi",
            ], 422);
        }

        $previous = $order->status;
        $order->update(['status' => $validated['status']]);

        // Kembalikan stok saat pesanan dibatalkan
        if ($validated['status'] === 'cancelled' && in_array($previous, ['pending', 'processing'], true)) {
            $this->restoreStock($order);
        }

        $order->load(['items.product', 'payments']);

        return response()->json([
            'status'  => 'success',
            'message' => "Status pesanan diubah menjadi {$validated['status']}",
            'data'    => $order,
        ]);
    }

    /**
     * Pindahkan item cart guest ke cart user saat login/checkout.
     */
    private function mergeGuestCart(string $userId, string $sessionId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->first();
        if (!$guestCart) {
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
     * Generate nomor order unik (retry saat collision).
     */
    private function generateOrderNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $number = 'ORD-' . now()->format('Ymd') . '-' . rand(1000, 9999);
            if (!Order::where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(substr((string) new ObjectId(), -6));
    }

    /**
     * List pesanan milik sendiri (user login / session guest).
     * Admin boleh lihat semua + filter user_id.
     * GET /api/orders?user_id=... (admin) / ?session_id=... (guest)
     */
    public function index(Request $request): JsonResponse
    {
        [$user, $sessionId] = $this->accessIdentity($request);

        $query = Order::with(['items.product', 'payments']);

        if ($user && $user->isAdmin()) {
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
                'status'  => 'error',
                'message' => 'Wajib login atau sertakan session_id',
            ], 401);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $orders->count(),
            'data'   => $orders,
        ]);
    }

    /**
     * Detail pesanan (pemilik atau admin).
     * GET /api/orders/{id}?session_id=... (guest)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'payments', 'user'])->find($id);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        if (!$this->canAccessOrder($request, $order)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak berhak melihat pesanan ini',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order,
        ]);
    }

    /**
     * Batalkan pesanan sendiri (hanya saat masih pending).
     * Stok dikembalikan. Pesanan berbayar ubah via admin.
     * POST /api/orders/{id}/cancel?session_id=... (guest)
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        if (!$this->canAccessOrder($request, $order)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak berhak membatalkan pesanan ini',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => "Pesanan {$order->status} tidak bisa dibatalkan sendiri, hubungi admin",
            ], 422);
        }

        $order->update(['status' => 'cancelled']);
        $this->restoreStock($order);
        $order->load(['items.product', 'payments']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pesanan dibatalkan, stok dikembalikan',
            'data'    => $order,
        ]);
    }

    /**
     * Kembalikan stok semua item pesanan.
     */
    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            \App\Models\Product::where('_id', $item->product_id)->increment('stock', $item->qty);
        }
    }
}
