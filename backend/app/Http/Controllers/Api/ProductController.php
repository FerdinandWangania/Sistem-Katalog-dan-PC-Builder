<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List katalog produk dengan filter dan search.
     * GET /api/products?category=CPU&brand=Intel&q=i9&sort=price_asc
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->query('brand'));
        }

        // PC Builder spec filters
        if ($request->filled('socket')) {
            $query->where('specs.socket', $request->query('socket'));
        }

        if ($request->filled('ram_type')) {
            $ramType = $request->query('ram_type');
            $query->where(function ($q) use ($ramType) {
                $q->where('specs.type', $ramType)
                  ->orWhere('specs.memory_type', $ramType);
            });
        }

        if ($request->filled('form_factor')) {
            $query->where('specs.form_factor', $request->query('form_factor'));
        }

        if ($request->filled('min_watt')) {
            $minWatt = (int) $request->query('min_watt');
            $query->where('specs.wattage', 'regex', '/^([0-9]+)/')
                  ->where(function ($q) use ($minWatt) {
                      // Ambil produk PSU
                      $q->where('category', 'PSU');
                  });
        }

        if ($request->filled('q')) {
            $search = $request->query('q');
            $query->where('name', 'regex', '/' . preg_quote($search, '/') . '/i');
        }

        if ($request->query('sort') === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($request->query('sort') === 'price_desc') {
            $query->orderBy('price', 'desc');
        }

        $products = $query->get();

        return response()->json([
            'status'  => 'success',
            'count'   => $products->count(),
            'data'    => $products,
        ]);
    }

    /**
     * Detail produk beserta spesifikasi.
     * GET /api/products/{id}
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $product,
        ]);
    }

    /**
     * Tambah produk baru (Admin).
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'       => 'required|string',
            'brand'          => 'required|string',
            'name'           => 'required|string',
            'price'          => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock'          => 'required|integer|min:0',
            'image'          => 'nullable|string|max:2048',
            'specs'          => 'nullable|array',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data'    => $product,
        ], 201);
    }

    /**
     * Update produk.
     * PUT /api/products/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'category'       => 'sometimes|string',
            'brand'          => 'sometimes|string',
            'name'           => 'sometimes|string',
            'price'          => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock'          => 'sometimes|integer|min:0',
            'image'          => 'nullable|string|max:2048',
            'specs'          => 'nullable|array',
        ]);

        $product->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data'    => $product,
        ]);
    }

    /**
     * Hapus produk.
     * DELETE /api/products/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Produk berhasil dihapus',
        ]);
    }
}
