<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class CartController extends Controller
{
    /**
     * Dapatkan keranjang belanja aktif.
     * GET /api/cart?user_id=... atau GET /api/cart?session_id=...
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $cart->load(['items.product']);

        $catalogItems = $cart->items->where('source_type', 'catalog')->values();
        $builderBuilds = $cart->items->where('source_type', 'builder')->groupBy('build_id')->map(function ($items, $buildId) {
            return [
                'build_id'    => $buildId,
                'total_price' => $items->sum(fn ($i) => $i->price_snapshot * $i->qty),
                'components'  => $items->values(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'cart_id'        => $cart->_id,
                'user_id'        => $cart->user_id,
                'session_id'     => $cart->session_id,
                'total_price'    => $cart->total_price,
                'item_count'     => $cart->items->sum('qty'),
                'catalog_items'  => $catalogItems,
                'builder_builds' => $builderBuilds,
            ],
        ]);
    }

    /**
     * Tambah produk ke keranjang (Bisa dari Katalog satuan atau PC Builder).
     * POST /api/cart/items
     * Body: {
     *   "user_id": "...",
     *   "product_id": "...",
     *   "qty": 1,
     *   "source_type": "catalog" | "builder",
     *   "build_id": "optional-uuid-rakitan"
     * }
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => 'nullable|string',
            'session_id'  => 'nullable|string',
            'product_id'  => 'required|string',
            'qty'         => 'nullable|integer|min:1',
            'source_type' => 'nullable|string|in:catalog,builder',
            'build_id'    => 'nullable|string',
        ]);

        $product = Product::find($validated['product_id']);
        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $cart = $this->resolveCart($request);
        $qty = $validated['qty'] ?? 1;
        $sourceType = $validated['source_type'] ?? 'catalog';
        $buildId = $validated['build_id'] ?? ($sourceType === 'builder' ? (string) new ObjectId() : null);

        // Cek stok tersedia (fail fast sebelum masuk keranjang)
        $existingQty = 0;
        if ($sourceType === 'catalog') {
            $existingQty = (int) (CartItem::where('cart_id', $cart->_id)
                ->where('product_id', $product->_id)
                ->where('source_type', 'catalog')
                ->first()?->qty ?? 0);
        }
        if ($product->stock < $existingQty + $qty) {
            return response()->json([
                'status'  => 'error',
                'message' => "Stok {$product->name} tidak mencukupi (tersedia: {$product->stock}).",
            ], 422);
        }

        // Jika katalog biasa dan produk sudah ada di keranjang, update qty
        if ($sourceType === 'catalog') {
            $existingItem = CartItem::where('cart_id', $cart->_id)
                ->where('product_id', $product->_id)
                ->where('source_type', 'catalog')
                ->first();

            if ($existingItem) {
                $existingItem->qty += $qty;
                $existingItem->price_snapshot = $product->effective_price;
                $existingItem->save();

                $this->recalculateCart($cart);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Jumlah produk di keranjang diperbarui',
                    'data'    => $existingItem,
                ]);
            }
        }

        // Buat CartItem baru
        $item = CartItem::create([
            'cart_id'        => $cart->_id,
            'product_id'     => $product->_id,
            'source_type'    => $sourceType,
            'build_id'       => $buildId,
            'qty'            => $qty,
            'price_snapshot' => $product->effective_price,
        ]);

        $this->recalculateCart($cart);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'data'    => $item,
        ], 201);
    }

    /**
     * Hapus item dari keranjang.
     * DELETE /api/cart/items/{id}?user_id=... atau ?session_id=...
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $item = CartItem::find($itemId);

        if (!$item) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Item keranjang tidak ditemukan',
            ], 404);
        }

        // Verifikasi kepemilikan: item harus milik cart user/session peminta
        if ($request->query('user_id') || $request->input('user_id')
            || $request->query('session_id') || $request->input('session_id')) {
            $cart = $this->resolveCart($request);
            if ((string) $item->cart_id !== (string) $cart->_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Item ini bukan milik keranjang Anda',
                ], 403);
            }
        } else {
            $cart = Cart::find($item->cart_id);
        }
        $item->delete();

        if ($cart) {
            $this->recalculateCart($cart);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Item berhasil dihapus dari keranjang',
        ]);
    }

    /**
     * Kosongkan keranjang.
     * POST /api/cart/clear
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        CartItem::where('cart_id', $cart->_id)->delete();
        $cart->total_price = 0;
        $cart->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Keranjang berhasil dikosongkan',
        ]);
    }

    /**
     * Cari atau buat keranjang belanja untuk user/session.
     */
    private function resolveCart(Request $request): Cart
    {
        $userId = $request->input('user_id', $request->query('user_id'));
        $sessionId = $request->input('session_id', $request->query('session_id'));

        if ($userId) {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['total_price' => 0]
            );
        }

        if ($sessionId) {
            return Cart::firstOrCreate(
                ['session_id' => $sessionId],
                ['total_price' => 0]
            );
        }

        // Default session untuk testing cepat
        return Cart::firstOrCreate(
            ['session_id' => 'guest-demo-session'],
            ['total_price' => 0]
        );
    }

    /**
     * Hitung ulang total keranjang.
     */
    private function recalculateCart(Cart $cart): void
    {
        $total = CartItem::where('cart_id', $cart->_id)
            ->get()
            ->sum(fn ($i) => $i->price_snapshot * $i->qty);

        $cart->total_price = (float) $total;
        $cart->save();
    }
}
