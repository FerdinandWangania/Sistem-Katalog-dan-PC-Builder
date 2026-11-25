<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\TokenAuth;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PackageItem;
use App\Models\Product;
use App\Services\PromotionService;
use App\Support\SpecNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService) {}

    /**
     * List katalog produk dengan filter, urutan, dan pagination.
     * GET /api/products?category=cpu&brand=Intel&socket=LGA1700&ram_type=DDR5
     *     &form_factor=ATX&min_watt=650&q=i9&sort=discount_desc&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string', 'max:100'],
            'brand' => ['sometimes', 'string', 'max:100'],
            'socket' => ['sometimes', 'string', 'max:100'],
            'ram_type' => ['sometimes', 'string', 'max:50'],
            'form_factor' => ['sometimes', 'string', 'max:50'],
            'min_watt' => ['sometimes', 'integer', 'min:0'],
            'q' => ['sometimes', 'string', 'max:200'],
            'sort' => ['sometimes', Rule::in(['price_asc', 'price_desc', 'discount_desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query();

        if (isset($validated['category'])) {
            $slug = SpecNormalizer::category($validated['category']);
            $pattern = '/^'.str_replace('_', '[ _]?', preg_quote($slug, '/')).'$/i';
            $query->where('category', 'regex', $pattern);
        }

        if (isset($validated['brand'])) {
            $query->where('brand', 'regex', '/^'.preg_quote($validated['brand'], '/').'$/i');
        }

        if (isset($validated['socket'])) {
            $query->where('specs.socket', 'regex', '/^'.preg_quote($validated['socket'], '/').'$/i');
        }

        if (isset($validated['ram_type'])) {
            $ramType = preg_quote(SpecNormalizer::memoryType($validated['ram_type']), '/');
            $query->where(function ($builder) use ($ramType): void {
                $builder->where('specs.type', 'regex', '/^'.$ramType.'$/i')
                    ->orWhere('specs.memory_type', 'regex', '/^'.$ramType.'$/i');
            });
        }

        if (isset($validated['form_factor'])) {
            $query->where('specs.form_factor', 'regex', '/^'.preg_quote($validated['form_factor'], '/').'$/i');
        }

        if (isset($validated['min_watt'])) {
            $query->where('specs.wattage_w', '>=', (int) $validated['min_watt']);
        }

        if (isset($validated['q'])) {
            $query->where('name', 'regex', '/'.preg_quote($validated['q'], '/').'/i');
        }

        $products = $query->get()->map(
            fn (Product $product): Product => $this->promotionService->decorate($product),
        );

        $sort = $validated['sort'] ?? null;

        if ($sort === 'price_asc') {
            $products = $products->sortBy(static fn (Product $product): float => (float) $product->price);
        } elseif ($sort === 'price_desc') {
            $products = $products->sortByDesc(static fn (Product $product): float => (float) $product->price);
        } elseif ($sort === 'discount_desc') {
            $products = $products->sortByDesc(static fn (Product $product): float => (float) $product->discount_percentage);
        } else {
            $products = $products->sortBy(static fn (Product $product): string => (string) $product->name);
        }

        $products = $products->values();

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $total = $products->count();
        $paged = $products->slice(($page - 1) * $perPage, $perPage)->values();

        if (! $this->canViewHpp($request)) {
            $paged->each->makeHidden('hpp');
        } else {
            $paged->each->makeVisible('hpp');
        }

        return response()->json([
            'status' => 'success',
            'count' => $paged->count(),
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Detail produk beserta spesifikasi.
     * GET /api/products/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $this->promotionService->decorate($product);

        if (! $this->canViewHpp($request)) {
            $product->makeHidden('hpp');
        } else {
            $product->makeVisible('hpp');
        }

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    /**
     * Tambah produk baru (Admin).
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $category = $this->normalizeCategory($validated['category']);

        if ($category === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak valid',
                'errors' => ['category' => ['Kategori wajib salah satu slug kategori yang tersedia']],
            ], 422);
        }

        $validated['category'] = $category;

        if (! $this->brandExists($validated['brand'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak terdaftar',
                'errors' => ['brand' => ['Brand wajib terdaftar di koleksi brands']],
            ], 422);
        }

        $product = Product::create($validated);
        $this->promotionService->decorate($product);

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product->makeVisible('hpp'),
        ], 201);
    }

    /**
     * Update produk.
     * PUT /api/products/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate($this->rules(true));

        if (isset($validated['category'])) {
            $category = $this->normalizeCategory($validated['category']);

            if ($category === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kategori tidak valid',
                    'errors' => ['category' => ['Kategori wajib salah satu slug kategori yang tersedia']],
                ], 422);
            }

            $validated['category'] = $category;
        }

        if (isset($validated['brand']) && ! $this->brandExists($validated['brand'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand tidak terdaftar',
                'errors' => ['brand' => ['Brand wajib terdaftar di koleksi brands']],
            ], 422);
        }

        $product->update($validated);
        $this->promotionService->decorate($product->fresh());

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $product->fresh()->makeVisible('hpp'),
        ]);
    }

    /**
     * Hapus produk; ditolak bila masih dipakai paket, keranjang, atau order aktif.
     * DELETE /api/products/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $productId = (string) $product->_id;

        if (PackageItem::where('product_id', $productId)->exists()) {
            return $this->deleteConflict('Produk tidak dapat dihapus karena masih dipakai paket prebuilt');
        }

        if (CartItem::where('product_id', $productId)->exists()) {
            return $this->deleteConflict('Produk tidak dapat dihapus karena masih ada di keranjang pembeli');
        }

        $orderIds = OrderItem::where('product_id', $productId)->pluck('order_id')->all();

        if ($orderIds !== [] && Order::whereIn('_id', $orderIds)
            ->whereIn('status', ['pending', 'processing', 'shipped'])
            ->exists()) {
            return $this->deleteConflict('Produk tidak dapat dihapus karena masih dipakai pesanan aktif');
        }

        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil dihapus',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'category' => [$presence, 'string'],
            'brand' => [$presence, 'string', 'max:100'],
            'name' => [$presence, 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price' => [$presence, 'numeric', 'min:0'],
            'discount_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'hpp' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => [$presence, 'integer', 'min:0'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:50'],
            'specs' => ['sometimes', 'nullable', 'array'],
        ];
    }

    private function normalizeCategory(string $category): ?string
    {
        $slug = SpecNormalizer::category($category);

        if ($slug === '') {
            return null;
        }

        $exists = Category::where('slug', $slug)->exists();

        if (! $exists && ! in_array($slug, Category::SLUGS, true)) {
            return null;
        }

        return $slug;
    }

    private function brandExists(string $brand): bool
    {
        $normalized = trim($brand);

        if ($normalized === '') {
            return false;
        }

        if (Brand::count() === 0) {
            return true;
        }

        return Brand::where('name', 'regex', '/^'.preg_quote($normalized, '/').'$/i')->exists();
    }

    private function canViewHpp(Request $request): bool
    {
        $user = TokenAuth::userFromHeader($request);

        return $user !== null && in_array($user->role, ['admin', 'superadmin', 'keuangan'], true);
    }

    private function deleteConflict(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 409);
    }
}
