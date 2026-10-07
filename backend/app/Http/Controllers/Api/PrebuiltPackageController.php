<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PackageItem;
use App\Models\PrebuiltPackage;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class PrebuiltPackageController extends Controller
{
    /**
     * List semua paket PC rakitan preset beserta komponennya.
     * GET /api/packages
     */
    public function index(): JsonResponse
    {
        $packages = PrebuiltPackage::with('items.product')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $packages->count(),
            'data'   => $packages,
        ]);
    }

    /**
     * Detail paket PC rakitan.
     * GET /api/packages/{id}
     */
    public function show(string $id): JsonResponse
    {
        $package = PrebuiltPackage::with('items.product')->find($id);

        if (!$package) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket PC tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $package,
        ]);
    }

    /**
     * Masukkan seluruh isi paket ke keranjang.
     * POST /api/packages/{id}/add-to-cart
     * Body: { "user_id": "..." } atau { "session_id": "..." }
     */
    public function addToCart(Request $request, string $id): JsonResponse
    {
        $package = PrebuiltPackage::with('items.product')->find($id);

        if (!$package) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket PC tidak ditemukan',
            ], 404);
        }

        $products = $package->items->map(fn ($i) => $i->product)->filter();
        if ($products->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket ini belum memiliki komponen',
            ], 422);
        }

        foreach ($products as $product) {
            if ($product->stock < 1) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Stok {$product->name} habis.",
                ], 422);
            }
        }

        $userId = $request->input('user_id');
        $sessionId = $request->input('session_id', 'guest-demo-session');

        $cart = $userId
            ? Cart::firstOrCreate(['user_id' => $userId], ['total_price' => 0])
            : Cart::firstOrCreate(['session_id' => $sessionId], ['total_price' => 0]);

        $buildId = (string) new ObjectId();
        $total = 0.0;
        foreach ($products as $product) {
            CartItem::create([
                'cart_id'        => $cart->_id,
                'product_id'     => $product->_id,
                'source_type'    => 'package',
                'build_id'       => $buildId,
                'qty'            => 1,
                'price_snapshot' => $product->effective_price,
            ]);
            $total += $product->effective_price;
        }

        $cart->recalculateTotal();

        return response()->json([
            'status'  => 'success',
            'message' => "Paket {$package->name} masuk keranjang!",
            'data'    => [
                'build_id'    => $buildId,
                'cart_id'     => $cart->_id,
                'total_build' => $total,
                'items_count' => $products->count(),
            ],
        ], 201);
    }

    /**
     * Buat paket baru (admin).
     * POST /api/packages  Body: { name, tag?, product_ids[] }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'tag'         => 'nullable|string|max:50',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|string',
        ]);

        $products = $this->resolveProducts($validated['product_ids']);
        if ($products === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Salah satu product_id tidak ditemukan',
            ], 422);
        }

        $package = PrebuiltPackage::create([
            'name'        => $validated['name'],
            'tag'         => $validated['tag'] ?? null,
            'total_price' => $products->sum(fn ($p) => $p->effective_price),
        ]);

        foreach ($products as $product) {
            PackageItem::create(['package_id' => $package->_id, 'product_id' => $product->_id]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Paket berhasil dibuat',
            'data'    => $package->load('items.product'),
        ], 201);
    }

    /**
     * Ubah paket (admin).
     * PUT /api/packages/{id}  Body: { name?, tag?, product_ids[]? }
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $package = PrebuiltPackage::find($id);
        if (!$package) {
            return response()->json(['status' => 'error', 'message' => 'Paket PC tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'tag'         => 'nullable|string|max:50',
            'product_ids' => 'sometimes|array|min:1',
            'product_ids.*' => 'required|string',
        ]);

        if (array_key_exists('name', $validated)) {
            $package->name = $validated['name'];
        }
        if (array_key_exists('tag', $validated)) {
            $package->tag = $validated['tag'];
        }
        if (array_key_exists('product_ids', $validated)) {
            $products = $this->resolveProducts($validated['product_ids']);
            if ($products === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Salah satu product_id tidak ditemukan',
                ], 422);
            }
            PackageItem::where('package_id', $package->_id)->delete();
            foreach ($products as $product) {
                PackageItem::create(['package_id' => $package->_id, 'product_id' => $product->_id]);
            }
            $package->total_price = $products->sum(fn ($p) => $p->effective_price);
        }
        $package->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Paket berhasil diperbarui',
            'data'    => $package->load('items.product'),
        ]);
    }

    /**
     * Hapus paket (admin).
     * DELETE /api/packages/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $package = PrebuiltPackage::find($id);
        if (!$package) {
            return response()->json(['status' => 'error', 'message' => 'Paket PC tidak ditemukan'], 404);
        }

        PackageItem::where('package_id', $package->_id)->delete();
        $package->delete();

        return response()->json(['status' => 'success', 'message' => 'Paket berhasil dihapus']);
    }

    /**
     * @return \Illuminate\Support\Collection|null
     */
    private function resolveProducts(array $ids)
    {
        $products = collect();
        foreach (array_unique($ids) as $pid) {
            $product = Product::find($pid);
            if (!$product) {
                return null;
            }
            $products->push($product);
        }

        return $products;
    }
}
