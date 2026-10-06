<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    /**
     * Checkout keranjang belanja menjadi Pesanan.
     * POST /api/orders/checkout
     * Body: {
     *   "user_id": "...",
     *   "shipping_address": "Jl. Sudirman No. 12, Jakarta",
     *   "shipping_fee": 25000,
     *   "gateway_provider": "midtrans"
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'          => 'required|string',
            'shipping_address' => 'required|string',
            'shipping_fee'     => 'nullable|numeric|min:0',
            'gateway_provider' => 'nullable|string|in:midtrans,xendit,bca_va,mandiri_va',
        ]);

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $cart = Cart::where('user_id', $validated['user_id'])->first();
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

        $shippingFee = (float) ($validated['shipping_fee'] ?? 25000);
        $subtotal = (float) $cartItems->sum(fn ($i) => $i->price_snapshot * $i->qty);
        $totalAmount = $subtotal + $shippingFee;

        // 1. Buat Order
        $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . rand(1000, 9999);
        $order = Order::create([
            'user_id'          => $user->_id,
            'order_number'     => $orderNumber,
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

        // 3. Buat Payment pending
        $gatewayProvider = $validated['gateway_provider'] ?? 'midtrans';
        $payment = Payment::create([
            'order_id'               => $order->_id,
            'gateway_provider'       => $gatewayProvider,
            'gateway_transaction_id' => 'TRX-' . strtoupper((string) new ObjectId()),
            'status'                 => 'pending',
            'amount'                 => $totalAmount,
            'paid_at'                => null,
        ]);

        // 4. Kosongkan keranjang
        CartItem::where('cart_id', $cart->_id)->delete();
        $cart->total_price = 0;
        $cart->save();

        // 5. Load relasi untuk respon
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
     * List pesanan (bisa filter user_id).
     * GET /api/orders?user_id=...
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items.product', 'payments']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $orders->count(),
            'data'   => $orders,
        ]);
    }

    /**
     * Detail pesanan.
     * GET /api/orders/{id}
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'payments', 'user'])->find($id);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order,
        ]);
    }
}
