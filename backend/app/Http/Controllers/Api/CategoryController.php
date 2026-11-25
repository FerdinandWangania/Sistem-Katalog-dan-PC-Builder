<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\SpecNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Kategori aktif untuk katalog publik.
     * GET /api/categories?type=component
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->where('is_active', '!=', false);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $categories = $query->orderBy('name')->get();
        $this->attachProductCounts($categories);

        return response()->json([
            'status' => 'success',
            'count' => $categories->count(),
            'data' => $categories,
        ]);
    }

    /**
     * Semua kategori untuk admin.
     * GET /api/admin/categories
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $categories = $query->orderBy('name')->get();
        $this->attachProductCounts($categories);

        return response()->json([
            'status' => 'success',
            'count' => $categories->count(),
            'data' => $categories,
        ]);
    }

    /**
     * Detail kategori berdasarkan ID atau slug.
     * GET /api/categories/{idOrSlug}
     */
    public function show(string $idOrSlug): JsonResponse
    {
        $category = $this->findCategory($idOrSlug);

        if (! $category || $category->is_active === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak ditemukan',
            ], 404);
        }

        $this->attachProductCounts(collect([$category]));

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    /**
     * Tambah kategori (admin).
     * POST /api/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', Rule::in(Category::SLUGS)],
            'type' => ['required', 'string', Rule::in(['component', 'package', 'accessory'])],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (Category::where('slug', $validated['slug'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Slug kategori sudah dipakai',
                'errors' => ['slug' => ['Slug kategori sudah dipakai']],
            ], 422);
        }

        $category = Category::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil dibuat',
            'data' => $category,
        ], 201);
    }

    /**
     * Ubah kategori (admin). Slug tidak boleh berubah bila sudah dipakai produk.
     * PUT /api/categories/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', Rule::in(Category::SLUGS)],
            'type' => ['sometimes', 'string', Rule::in(['component', 'package', 'accessory'])],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['slug']) && $validated['slug'] !== $category->slug) {
            if (Category::where('slug', $validated['slug'])->where('_id', '!=', $category->_id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Slug kategori sudah dipakai',
                    'errors' => ['slug' => ['Slug kategori sudah dipakai']],
                ], 422);
            }

            if (($this->productCounts()[$category->slug] ?? 0) > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Slug kategori tidak dapat diubah karena masih dipakai produk',
                ], 409);
            }
        }

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil diperbarui',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Hapus kategori (admin). Ditolak bila masih dipakai produk/promo.
     * DELETE /api/categories/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak ditemukan',
            ], 404);
        }

        if (($this->productCounts()[$category->slug] ?? 0) > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak dapat dihapus karena masih dipakai produk',
            ], 409);
        }

        if (Promotion::where('click_target_type', 'category')
            ->where('click_target_value', $category->slug)
            ->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak dapat dihapus karena dipakai promo',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil dihapus',
        ]);
    }

    private function findCategory(string $idOrSlug): ?Category
    {
        try {
            $category = Category::find($idOrSlug);
        } catch (\Throwable) {
            $category = null;
        }

        return $category ?? Category::where('slug', $idOrSlug)->first();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function attachProductCounts($categories): void
    {
        $counts = $this->productCounts();

        foreach ($categories as $category) {
            $category->setAttribute('product_count', $counts[$category->slug] ?? 0);
        }
    }

    /**
     * @return array<string, int>
     */
    private function productCounts(): array
    {
        return Product::query()
            ->get(['category'])
            ->groupBy(static fn (Product $product): string => SpecNormalizer::category($product->category))
            ->map(static fn ($products): int => $products->count())
            ->all();
    }
}
