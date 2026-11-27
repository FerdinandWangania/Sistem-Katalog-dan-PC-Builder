<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CompatibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

/**
 * PCBuilderController — endpoint PC Builder.
 *
 * Seluruh aturan kompatibilitas berada di App\Services\CompatibilityService
 * agar endpoint builder dan revalidasi checkout memakai logika yang sama.
 */
class PCBuilderController extends Controller
{
    public function __construct(private CompatibilityService $compatibility) {}

    /**
     * Validasi Kompatibilitas Rakitan PC & Kalkulator Daya.
     * POST /api/builder/validate
     */
    public function validateBuild(Request $request): JsonResponse
    {
        $analysis = $this->compatibility->analyze($request->all(), [
            'build_name' => $request->input('build_name'),
            'ignore_psu_warning' => $request->boolean('ignore_psu_warning'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $analysis,
        ]);
    }

    /**
     * Masukkan seluruh komponen rakitan ke keranjang sebagai satu grup.
     * POST /api/builder/add-to-cart
     */
    public function addBuildToCart(Request $request): JsonResponse
    {
        $analysis = $this->compatibility->analyze($request->all(), [
            'build_name' => $request->input('build_name'),
            'ignore_psu_warning' => $request->boolean('ignore_psu_warning'),
            'quantities' => $request->input('quantities'),
        ]);

        if (! $analysis['is_compatible']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rakitan PC memiliki masalah kompatibilitas fisik/daya!',
                'errors' => $analysis['errors'],
                'details' => $analysis['error_details'],
            ], 422);
        }

        $cart = $this->resolveCart($request);

        if (! $cart) {
            return response()->json([
                'status' => 'error',
                'message' => 'user_id atau session_id wajib diisi',
            ], 422);
        }

        $buildId = (string) new ObjectId;
        $buildName = $analysis['build_name'];
        $created = [];

        try {
            foreach ($analysis['resolved_products'] as $product) {
                $item = CartItem::create([
                    'cart_id' => $cart->_id,
                    'product_id' => $product->_id,
                    'source_type' => 'builder',
                    'build_id' => $buildId,
                    'build_name' => $buildName,
                    'qty' => $analysis['quantities'][(string) $product->_id] ?? 1,
                    'price_snapshot' => $product->effective_price,
                ]);
                $created[] = (string) $item->_id;
            }
        } catch (\Throwable $e) {
            CartItem::whereIn('_id', $created)->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan rakitan ke keranjang, silakan coba lagi.',
            ], 500);
        }

        $this->recalculateCart($cart);

        return response()->json([
            'status' => 'success',
            'message' => 'Seluruh komponen rakitan PC berhasil dimasukkan ke keranjang belanja!',
            'data' => [
                'build_id' => $buildId,
                'build_name' => $buildName,
                'cart_id' => $cart->_id,
                'total_build' => $analysis['grand_total'],
                'items_count' => count($created),
                'power_analysis' => $analysis['power_analysis'],
            ],
        ], 201);
    }

    /**
     * Ganti isi satu rakitan secara penuh (full-replace) dengan revalidasi.
     * PUT /api/builder/builds/{buildId}
     */
    public function replaceBuild(Request $request, string $buildId): JsonResponse
    {
        $cart = $this->resolveCart($request, true);

        if (! $cart) {
            return response()->json([
                'status' => 'error',
                'message' => 'user_id atau session_id wajib diisi',
            ], 422);
        }

        $items = CartItem::where('cart_id', $cart->_id)
            ->where('build_id', $buildId)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rakitan tidak ditemukan di keranjang',
            ], 404);
        }

        $analysis = $this->compatibility->analyze($request->all(), [
            'build_name' => $request->input('build_name'),
            'ignore_psu_warning' => $request->boolean('ignore_psu_warning'),
            'quantities' => $request->input('quantities'),
        ]);

        if (! $analysis['is_compatible']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rakitan pengganti tidak kompatibel, perubahan dibatalkan.',
                'errors' => $analysis['errors'],
                'details' => $analysis['error_details'],
            ], 422);
        }

        $snapshots = $items->map(fn (CartItem $item) => $item->only([
            'cart_id',
            'product_id',
            'source_type',
            'build_id',
            'build_name',
            'qty',
            'price_snapshot',
        ]))->all();

        $newIds = [];

        try {
            CartItem::where('cart_id', $cart->_id)->where('build_id', $buildId)->delete();

            foreach ($analysis['resolved_products'] as $product) {
                $item = CartItem::create([
                    'cart_id' => $cart->_id,
                    'product_id' => $product->_id,
                    'source_type' => 'builder',
                    'build_id' => $buildId,
                    'build_name' => $analysis['build_name'],
                    'qty' => $analysis['quantities'][(string) $product->_id] ?? 1,
                    'price_snapshot' => $product->effective_price,
                ]);
                $newIds[] = (string) $item->_id;
            }
        } catch (\Throwable $e) {
            // Rollback: hapus item baru yang sempat dibuat, pulihkan snapshot lama.
            CartItem::whereIn('_id', $newIds)->delete();

            foreach ($snapshots as $row) {
                CartItem::create($row);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengganti rakitan, perubahan dibatalkan.',
            ], 500);
        }

        $this->recalculateCart($cart);

        return response()->json([
            'status' => 'success',
            'message' => 'Rakitan berhasil diperbarui',
            'data' => [
                'build_id' => $buildId,
                'build_name' => $analysis['build_name'],
                'cart_id' => $cart->_id,
                'grand_total' => $analysis['grand_total'],
                'items_count' => count($newIds),
                'power_analysis' => $analysis['power_analysis'],
            ],
        ]);
    }

    private function resolveCart(Request $request, bool $requireIdentity = false): ?Cart
    {
        $userId = $request->input('user_id', $request->query('user_id'));
        $sessionId = $request->input('session_id', $request->query('session_id'));

        if ($userId) {
            return Cart::firstOrCreate(['user_id' => $userId], ['total_price' => 0]);
        }

        if ($sessionId) {
            return Cart::firstOrCreate(['session_id' => $sessionId], ['total_price' => 0]);
        }

        if ($requireIdentity) {
            return null;
        }

        return Cart::firstOrCreate(['session_id' => 'guest-demo-session'], ['total_price' => 0]);
    }

    private function recalculateCart(Cart $cart): void
    {
        $cart->total_price = (float) CartItem::where('cart_id', $cart->_id)
            ->get()
            ->sum(fn (CartItem $item) => $item->price_snapshot * $item->qty);

        $cart->save();
    }
}
