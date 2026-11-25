<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\TokenAuth;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService) {}

    /**
     * Promo yang sedang aktif untuk homepage/promo list.
     * GET /api/promotions
     */
    public function index(): JsonResponse
    {
        $promotions = Promotion::active()
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $promotions->count(),
            'data' => $promotions,
        ]);
    }

    /**
     * Semua promo untuk admin.
     * GET /api/admin/promotions
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Promotion::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('active_now')) {
            $now = now();
            $query->where('is_active', '!=', false)
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now);
        }

        $promotions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'count' => $promotions->count(),
            'data' => $promotions,
        ]);
    }

    /**
     * Detail promo; admin dapat melihat promo nonaktif.
     * GET /api/promotions/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion || (! $promotion->isActive() && ! $this->isAdminRequest($request))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $promotion,
        ]);
    }

    /**
     * Produk dalam sebuah promo, diurutkan dari diskon nominal terbesar.
     * GET /api/promotions/{id}/products
     */
    public function products(Request $request, string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion || (! $promotion->isActive() && ! $this->isAdminRequest($request))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo tidak ditemukan',
            ], 404);
        }

        $products = $this->productsForPromotion($promotion)
            ->map(function (Product $product) use ($promotion): Product {
                $promoPrice = round((float) $product->price * (1 - ((float) $promotion->discount_percentage / 100)), 2);

                return $product->setAttribute('promo_discount_percentage', (float) $promotion->discount_percentage)
                    ->setAttribute('promo_price', $promoPrice)
                    ->setAttribute('effective_price', $promoPrice)
                    ->setAttribute(
                        'discount_percentage',
                        (float) $product->price > 0
                            ? round((((float) $product->price - $promoPrice) / (float) $product->price) * 100, 2)
                            : 0.0,
                    );
            })
            ->sortByDesc(static fn (Product $product): float => (float) $product->price - (float) $product->promo_price)
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'promotion' => $promotion,
                'products' => $products,
                'count' => $products->count(),
            ],
        ]);
    }

    /**
     * Buat promo (admin). Bila produk terpilih masih punya diskon manual,
     * endpoint mengembalikan 422 berisi daftar konflik sampai admin memilih
     * manual_discount_action: override atau skip.
     * POST /api/promotions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (isset($validated['product_ids'])) {
            $missing = $this->missingProductIds($validated['product_ids']);

            if ($missing !== []) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Produk promo tidak ditemukan',
                    'errors' => ['product_ids' => ['Produk promo tidak ditemukan: '.implode(', ', $missing)]],
                ], 422);
            }
        }

        $conflicts = $this->manualDiscountConflicts(
            $validated['product_ids'] ?? [],
            $validated['brands'] ?? [],
            (float) ($validated['discount_percentage'] ?? 0),
        );

        $action = $validated['manual_discount_action'] ?? null;

        if ($conflicts !== [] && $action === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sebagian produk masih memiliki diskon manual. Pilih Timpa atau Lewati.',
                'errors' => ['manual_discount_action' => ['Pilih override atau skip untuk produk dengan diskon manual']],
                'data' => [
                    'manual_discount_conflicts' => $conflicts,
                ],
            ], 422);
        }

        $decisions = $this->resolveManualDiscountDecisions(
            $conflicts,
            $action,
            $validated['manual_discount_product_ids'] ?? null,
            true,
        );

        $promotion = Promotion::create([
            ...$this->onlyPromotionFields($validated),
            'excluded_product_ids' => $decisions['excluded_product_ids'],
            'override_manual_discount' => $decisions['override_manual_discount'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->promotionService->flush();

        return response()->json([
            'status' => 'success',
            'message' => 'Promo berhasil dibuat',
            'data' => $promotion,
            'manual_discount_conflicts' => $conflicts,
        ], 201);
    }

    /**
     * Ubah promo (admin).
     * PUT /api/promotions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo tidak ditemukan',
            ], 404);
        }

        $validated = $this->validatePayload($request, false, $promotion);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (isset($validated['product_ids'])) {
            $missing = $this->missingProductIds($validated['product_ids']);

            if ($missing !== []) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Produk promo tidak ditemukan',
                    'errors' => ['product_ids' => ['Produk promo tidak ditemukan: '.implode(', ', $missing)]],
                ], 422);
            }
        }

        $productIds = $validated['product_ids'] ?? $promotion->product_ids ?? [];
        $brands = $validated['brands'] ?? $promotion->brands ?? [];
        $conflicts = $this->manualDiscountConflicts(
            $productIds,
            $brands,
            (float) ($validated['discount_percentage'] ?? $promotion->discount_percentage ?? 0),
        );

        $action = $validated['manual_discount_action']
            ?? (($promotion->override_manual_discount ?? true) ? 'override' : 'skip');

        $decisions = $this->resolveManualDiscountDecisions(
            $conflicts,
            $action,
            $validated['manual_discount_product_ids'] ?? null,
            true,
        );

        $promotion->update([
            ...$this->onlyPromotionFields($validated),
            'excluded_product_ids' => $decisions['excluded_product_ids'],
            'override_manual_discount' => $decisions['override_manual_discount'],
        ]);

        $this->promotionService->flush();

        return response()->json([
            'status' => 'success',
            'message' => 'Promo berhasil diperbarui',
            'data' => $promotion->fresh(),
            'manual_discount_conflicts' => $conflicts,
        ]);
    }

    /**
     * Hapus promo (admin).
     * DELETE /api/promotions/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (! $promotion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo tidak ditemukan',
            ], 404);
        }

        $promotion->delete();
        $this->promotionService->flush();

        return response()->json([
            'status' => 'success',
            'message' => 'Promo berhasil dihapus',
        ]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validatePayload(Request $request, bool $creating, ?Promotion $promotion = null): array|JsonResponse
    {
        $required = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'banner_image' => [$required, 'string', 'max:2048'],
            'click_target_type' => ['sometimes', 'nullable', Rule::in(['brand', 'category', 'product', 'package', 'promo', 'url'])],
            'click_target_value' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'brands' => ['sometimes', 'array'],
            'brands.*' => ['string', 'max:100'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['string'],
            'discount_percentage' => [$required, 'numeric', 'min:0', 'max:100'],
            'start_date' => [$required, 'date'],
            'end_date' => [$creating ? 'required' : 'sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'manual_discount_action' => ['sometimes', 'nullable', Rule::in(['override', 'skip'])],
            'manual_discount_product_ids' => ['sometimes', 'array'],
            'manual_discount_product_ids.*' => ['string'],
        ]);

        $start = $validated['start_date'] ?? $promotion?->start_date;
        $end = $validated['end_date'] ?? $promotion?->end_date;

        if ($start !== null && $end !== null && strtotime((string) $end) < strtotime((string) $start)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanggal berakhir harus setelah tanggal mulai',
                'errors' => ['end_date' => ['Tanggal berakhir harus setelah tanggal mulai']],
            ], 422);
        }

        if (isset($validated['click_target_type']) && $validated['click_target_type'] !== null) {
            if (empty($validated['click_target_value'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'click_target_value wajib diisi bila click_target_type dipilih',
                    'errors' => ['click_target_value' => ['Nilai target wajib diisi']],
                ], 422);
            }

            if ($validated['click_target_type'] === 'url' && ! filter_var($validated['click_target_value'], FILTER_VALIDATE_URL)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'URL target tidak valid',
                    'errors' => ['click_target_value' => ['URL target tidak valid']],
                ], 422);
            }
        }

        if (isset($validated['brands'])) {
            $validated['brands'] = array_values(array_filter(array_unique(array_map(
                static fn ($brand): string => trim((string) $brand),
                $validated['brands'],
            ))));
        }

        if (isset($validated['product_ids'])) {
            $validated['product_ids'] = array_values(array_unique(array_map(
                static fn ($productId): string => (string) $productId,
                $validated['product_ids'],
            )));
        }

        $effectiveBrands = $validated['brands'] ?? $promotion?->brands ?? [];
        $effectiveProductIds = $validated['product_ids'] ?? $promotion?->product_ids ?? [];

        if (empty($effectiveBrands) && empty($effectiveProductIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pilih minimal satu brand atau produk untuk promo',
                'errors' => ['brands' => ['Pilih minimal satu brand atau produk untuk promo']],
            ], 422);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function onlyPromotionFields(array $validated): array
    {
        return array_intersect_key($validated, array_flip([
            'title',
            'banner_image',
            'click_target_type',
            'click_target_value',
            'brands',
            'product_ids',
            'discount_percentage',
            'start_date',
            'end_date',
            'is_active',
        ]));
    }

    /**
     * @param  array<int, string>  $productIds
     * @param  array<int, string>  $brands
     * @return array<int, array<string, mixed>>
     */
    private function manualDiscountConflicts(array $productIds, array $brands, float $promoPercentage): array
    {
        $products = $productIds !== []
            ? Product::whereIn('_id', $productIds)->get()
            : $this->productsForBrands($brands);

        return $products
            ->filter(static fn (Product $product): bool => (float) $product->discount_price > 0)
            ->map(static function (Product $product) use ($promoPercentage): array {
                $price = (float) $product->price;
                $manualPrice = (float) $product->discount_price;
                $promoPrice = round($price * (1 - ($promoPercentage / 100)), 2);

                return [
                    'product_id' => (string) $product->_id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $price,
                    'manual_discount_price' => $manualPrice,
                    'manual_discount_percentage' => $price > 0 ? round((($price - $manualPrice) / $price) * 100, 2) : 0.0,
                    'promo_price' => $promoPrice,
                    'promo_discount_percentage' => $promoPercentage,
                    'nominal_before' => $manualPrice,
                    'nominal_after' => $promoPrice,
                    'difference' => round($manualPrice - $promoPrice, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<int, string>|null  $overrideIds
     * @return array{excluded_product_ids: array<int, string>, override_manual_discount: bool}
     */
    private function resolveManualDiscountDecisions(
        array $conflicts,
        ?string $action,
        ?array $overrideIds,
        bool $defaultOverride,
    ): array {
        if ($conflicts === []) {
            return [
                'excluded_product_ids' => [],
                'override_manual_discount' => true,
            ];
        }

        $conflictIds = array_map(static fn (array $conflict): string => (string) $conflict['product_id'], $conflicts);

        if ($action === 'override') {
            $selectedOverrideIds = $overrideIds === null
                ? $conflictIds
                : array_values(array_intersect($conflictIds, array_map('strval', $overrideIds)));

            return [
                'excluded_product_ids' => array_values(array_diff($conflictIds, $selectedOverrideIds)),
                'override_manual_discount' => true,
            ];
        }

        if ($action === 'skip') {
            return [
                'excluded_product_ids' => $conflictIds,
                'override_manual_discount' => false,
            ];
        }

        return $defaultOverride
            ? ['excluded_product_ids' => [], 'override_manual_discount' => true]
            : ['excluded_product_ids' => $conflictIds, 'override_manual_discount' => false];
    }

    /**
     * @param  array<int, string>  $brands
     * @return Collection<int, Product>
     */
    private function productsForBrands(array $brands)
    {
        $normalized = array_map(static fn ($brand): string => strtolower(trim((string) $brand)), $brands);

        return Product::query()
            ->get()
            ->filter(static fn (Product $product): bool => in_array(strtolower((string) $product->brand), $normalized, true))
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    private function productsForPromotion(Promotion $promotion)
    {
        $productIds = $promotion->product_ids ?? [];
        $products = $productIds !== []
            ? Product::whereIn('_id', $productIds)->get()
            : $this->productsForBrands($promotion->brands ?? []);

        $excluded = array_map('strval', $promotion->excluded_product_ids ?? []);

        return $products
            ->reject(static fn (Product $product): bool => in_array((string) $product->_id, $excluded, true))
            ->values();
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<int, string>
     */
    private function missingProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $found = Product::whereIn('_id', $productIds)->get()->map(
            static fn (Product $product): string => (string) $product->_id,
        )->all();

        return array_values(array_diff($productIds, $found));
    }

    private function isAdminRequest(Request $request): bool
    {
        $user = TokenAuth::userFromHeader($request);

        return $user !== null && in_array($user->role, ['admin', 'superadmin', 'keuangan'], true);
    }
}
