<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesIdentity;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;

class CartController extends Controller
{
    use ResolvesIdentity;

    /**
     * Dapatkan keranjang belanja aktif.
     * GET /api/cart?session_id=... (tamu)
     */
    public function show(Request $request): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $cart = $this->resolveCart($request, $identity);
        $cart->load(['items.product']);

        $catalogItems = $cart->items->where('source_type', 'catalog')->values();
        $builderBuilds = $this->groupBuilds($cart, 'builder');
        $packageBuilds = $this->groupBuilds($cart, 'package');

        return response()->json([
            'status' => 'success',
            'data' => [
                'cart_id' => $cart->_id,
                'user_id' => $cart->user_id,
                'session_id' => $cart->session_id,
                'total_price' => $cart->total_price,
                'item_count' => $cart->items->sum('qty'),
                'catalog_items' => $catalogItems,
                'builder_builds' => $builderBuilds,
                'package_builds' => $packageBuilds,
            ],
        ]);
    }

    /**
     * Tambah produk ke keranjang (katalog satuan atau PC Builder).
     * POST /api/cart/items
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string'],
            'product_id' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', 'string', 'in:catalog,builder'],
            'build_id' => ['nullable', 'string'],
        ]);

        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $product = Product::find($validated['product_id']);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $cart = $this->resolveCart($request, $identity);
        $qty = (int) ($validated['qty'] ?? 1);
        $sourceType = $validated['source_type'] ?? 'catalog';
        $buildId = $validated['build_id'] ?? ($sourceType === 'builder' ? (string) new ObjectId : null);

        $existingQty = 0;

        if ($sourceType === 'catalog') {
            $existingQty = (int) (CartItem::where('cart_id', $cart->_id)
                ->where('product_id', $product->_id)
                ->where('source_type', 'catalog')
                ->first()?->qty ?? 0);
        }

        if ($product->stock < $existingQty + $qty) {
            return response()->json([
                'status' => 'error',
                'message' => "Stok {$product->name} tidak mencukupi (tersedia: {$product->stock}).",
            ], 422);
        }

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
                    'status' => 'success',
                    'message' => 'Jumlah produk di keranjang diperbarui',
                    'data' => $existingItem,
                ]);
            }
        }

        $item = CartItem::create([
            'cart_id' => $cart->_id,
            'product_id' => $product->_id,
            'source_type' => $sourceType,
            'build_id' => $buildId,
            'qty' => $qty,
            'price_snapshot' => $product->effective_price,
        ]);

        $this->recalculateCart($cart);

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'data' => $item,
        ], 201);
    }

    /**
     * Hapus item dari keranjang milik sendiri.
     * DELETE /api/cart/items/{id}?session_id=...
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $item = CartItem::find($itemId);

        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item keranjang tidak ditemukan',
            ], 404);
        }

        $cart = $this->resolveCart($request, $identity);

        if ((string) $item->cart_id !== (string) $cart->_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item ini bukan milik keranjang Anda',
            ], 403);
        }

        $item->delete();
        $this->recalculateCart($cart);

        return response()->json([
            'status' => 'success',
            'message' => 'Item berhasil dihapus dari keranjang',
        ]);
    }

    /**
     * Kosongkan keranjang.
     * POST /api/cart/clear
     */
    public function clear(Request $request): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $cart = $this->resolveCart($request, $identity);

        CartItem::where('cart_id', $cart->_id)->delete();
        $cart->total_price = 0;
        $cart->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Keranjang berhasil dikosongkan',
        ]);
    }

    /**
     * @param  array{user_id: string|null, session_id: string|null, user: User|null}  $identity
     */
    private function resolveCart(Request $request, array $identity): Cart
    {
        if ($identity['user_id'] !== null) {
            return Cart::firstOrCreate(
                ['user_id' => $identity['user_id']],
                ['total_price' => 0],
            );
        }

        return Cart::firstOrCreate(
            ['session_id' => $identity['session_id']],
            ['total_price' => 0],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function groupBuilds(Cart $cart, string $sourceType)
    {
        return $cart->items->where('source_type', $sourceType)
            ->groupBy('build_id')
            ->map(static function ($items, $buildId): array {
                return [
                    'build_id' => $buildId,
                    'build_name' => $items->first()?->build_name,
                    'total_price' => $items->sum(static fn ($item): float => (float) $item->price_snapshot * (int) $item->qty),
                    'components' => $items->values(),
                ];
            })
            ->values();
    }

    private function recalculateCart(Cart $cart): void
    {
        $total = CartItem::where('cart_id', $cart->_id)
            ->get()
            ->sum(static fn ($item): float => (float) $item->price_snapshot * (int) $item->qty);

        $cart->total_price = (float) $total;
        $cart->save();
    }

    private function identityRequired(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Wajib login atau sertakan session_id',
            'errors' => ['session_id' => ['Wajib login atau sertakan session_id']],
        ], 422);
    }
}
