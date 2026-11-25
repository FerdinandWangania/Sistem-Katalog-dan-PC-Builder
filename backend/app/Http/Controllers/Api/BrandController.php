<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BrandController extends Controller
{
    /**
     * Brand aktif untuk katalog publik.
     * GET /api/brands?q=asus
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()->where('is_active', '!=', false);

        if ($request->filled('q')) {
            $query->where('name', 'regex', '/'.preg_quote($request->string('q')->toString(), '/').'/i');
        }

        $brands = $query->orderBy('name')->get();
        $this->attachProductCounts($brands);

        return response()->json([
            'status' => 'success',
            'count' => $brands->count(),
            'data' => $brands,
        ]);
    }

    /**
     * Semua brand untuk admin.
     * GET /api/admin/brands
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Brand::query();

        if ($request->filled('q')) {
            $query->where('name', 'regex', '/'.preg_quote($request->string('q')->toString(), '/').'/i');
        }

        $brands = $query->orderBy('name')->get();
        $this->attachProductCounts($brands);

        return response()->json([
            'status' => 'success',
            'count' => $brands->count(),
            'data' => $brands,
        ]);
    }

    /**
     * Tambah brand (admin).
     * POST /api/brands
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($this->nameExists($validated['name'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nama brand sudah dipakai',
                'errors' => ['name' => ['Nama brand sudah dipakai']],
            ], 422);
        }

        $brand = Brand::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Brand berhasil dibuat',
            'data' => $brand,
        ], 201);
    }

    /**
     * Ubah brand (admin). Nama tidak boleh berubah bila sudah dipakai produk.
     * PUT /api/brands/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name']) && strcasecmp($validated['name'], (string) $brand->name) !== 0) {
            if ($this->nameExists($validated['name'], (string) $brand->_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nama brand sudah dipakai',
                    'errors' => ['name' => ['Nama brand sudah dipakai']],
                ], 422);
            }

            if (($this->productCounts()[strtolower((string) $brand->name)] ?? 0) > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nama brand tidak dapat diubah karena masih dipakai produk',
                ], 409);
            }
        }

        $brand->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Brand berhasil diperbarui',
            'data' => $brand->fresh(),
        ]);
    }

    /**
     * Hapus brand (admin). Ditolak bila masih dipakai produk/promo.
     * DELETE /api/brands/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak ditemukan',
            ], 404);
        }

        if (($this->productCounts()[strtolower((string) $brand->name)] ?? 0) > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak dapat dihapus karena masih dipakai produk',
            ], 409);
        }

        if ($this->usedByPromotion((string) $brand->name)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak dapat dihapus karena dipakai promo',
            ], 409);
        }

        $brand->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Brand berhasil dihapus',
        ]);
    }

    private function nameExists(string $name, ?string $exceptId = null): bool
    {
        $query = Brand::where('name', 'regex', '/^'.preg_quote($name, '/').'$/i');

        if ($exceptId !== null) {
            $query->where('_id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function usedByPromotion(string $brand): bool
    {
        $promotionUsesBrand = Promotion::query()
            ->get(['brands', 'click_target_type', 'click_target_value'])
            ->contains(fn (Promotion $promotion): bool => $this->brandListContains($promotion->brands, $brand)
                || ($promotion->click_target_type === 'brand'
                    && strcasecmp((string) $promotion->click_target_value, $brand) === 0));

        if ($promotionUsesBrand) {
            return true;
        }

        return PromoCode::query()
            ->get(['brands'])
            ->contains(fn (PromoCode $promoCode): bool => $this->brandListContains($promoCode->brands, $brand));
    }

    /**
     * @param  array<int, mixed>|null  $brands
     */
    private function brandListContains(?array $brands, string $brand): bool
    {
        foreach ($brands ?? [] as $value) {
            if (strcasecmp(trim((string) $value), $brand) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Brand>  $brands
     */
    private function attachProductCounts($brands): void
    {
        $counts = $this->productCounts();

        foreach ($brands as $brand) {
            $brand->setAttribute('product_count', $counts[strtolower((string) $brand->name)] ?? 0);
        }
    }

    /**
     * @return array<string, int>
     */
    private function productCounts(): array
    {
        return Product::query()
            ->get(['brand'])
            ->groupBy(static fn (Product $product): string => strtolower((string) $product->brand))
            ->map(static fn ($products): int => $products->count())
            ->all();
    }
}
