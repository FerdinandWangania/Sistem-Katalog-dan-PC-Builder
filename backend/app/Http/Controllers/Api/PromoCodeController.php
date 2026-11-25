<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesIdentity;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    use ResolvesIdentity;

    public function __construct(private readonly PromoCodeService $promoCodeService) {}

    /**
     * Daftar kode promo (admin).
     * GET /api/admin/promo-codes
     */
    public function index(Request $request): JsonResponse
    {
        $query = PromoCode::query();

        if ($request->filled('q')) {
            $query->where('code', 'regex', '/'.preg_quote($request->string('q')->toString(), '/').'/i');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('active_now')) {
            $now = now();
            $query->where('is_active', true)
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now);
        }

        $codes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'count' => $codes->count(),
            'data' => $codes,
        ]);
    }

    /**
     * Buat kode promo (admin).
     * POST /api/promo-codes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'brands' => ['sometimes', 'nullable', 'array'],
            'brands.*' => ['string', 'max:100'],
            'min_subtotal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $code = $this->normalizeCode($validated['code']);

        if (PromoCode::where('code', $code)->exists()) {
            return $this->duplicateCode();
        }

        $promoCode = PromoCode::create([
            ...$validated,
            'code' => $code,
            'brands' => $this->normalizeBrands($validated['brands'] ?? null),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode promo berhasil dibuat',
            'data' => $promoCode,
        ], 201);
    }

    /**
     * Ubah kode promo (admin).
     * PUT /api/promo-codes/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $promoCode = PromoCode::find($id);

        if (! $promoCode) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50'],
            'discount_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'brands' => ['sometimes', 'nullable', 'array'],
            'brands.*' => ['string', 'max:100'],
            'min_subtotal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $code = $this->normalizeCode($validated['code']);
            $duplicate = PromoCode::where('code', $code)->where('_id', '!=', $promoCode->_id)->exists();

            if ($duplicate) {
                return $this->duplicateCode();
            }

            $validated['code'] = $code;
        }

        if (array_key_exists('brands', $validated)) {
            $validated['brands'] = $this->normalizeBrands($validated['brands']);
        }

        $start = $validated['start_date'] ?? $promoCode->start_date;
        $end = $validated['end_date'] ?? $promoCode->end_date;

        if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanggal berakhir harus setelah tanggal mulai',
                'errors' => ['end_date' => ['Tanggal berakhir harus setelah tanggal mulai']],
            ], 422);
        }

        $promoCode->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode promo berhasil diperbarui',
            'data' => $promoCode->fresh(),
        ]);
    }

    /**
     * Hapus kode promo (admin).
     * DELETE /api/promo-codes/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $promoCode = PromoCode::find($id);

        if (! $promoCode) {
            return $this->notFound();
        }

        $promoCode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kode promo berhasil dihapus',
        ]);
    }

    /**
     * Validasi kode promo terhadap keranjang pemilik.
     * POST /api/promo-codes/validate
     */
    public function validateCode(Request $request): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wajib login atau sertakan session_id',
                'errors' => ['session_id' => ['Wajib login atau sertakan session_id']],
            ], 422);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $promoCode = PromoCode::where('code', $this->normalizeCode($validated['code']))->first();

        if (! $promoCode) {
            return $this->invalidCode('Kode promo tidak ditemukan');
        }

        if (! $promoCode->is_active) {
            return $this->invalidCode('Kode promo tidak aktif');
        }

        if (! $promoCode->start_date || ! $promoCode->end_date || ! now()->between($promoCode->start_date, $promoCode->end_date)) {
            return $this->invalidCode('Kode promo di luar masa berlaku');
        }

        $cart = Cart::where($this->identityQuery($identity))->first();

        if (! $cart) {
            return $this->invalidCode('Keranjang masih kosong');
        }

        $result = $this->promoCodeService->validateForCart($promoCode, $cart);

        if (! $result['valid']) {
            return $this->invalidCode($result['message'], $result);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => [
                'code' => $promoCode->code,
                'discount_percentage' => $promoCode->discount_percentage,
                'min_subtotal' => $promoCode->min_subtotal,
                'brands' => $promoCode->brands,
                'subtotal' => $result['subtotal'],
                'eligible_subtotal' => $result['eligible_subtotal'],
                'discount_amount' => $result['discount_amount'],
                'total_after_discount' => $result['total_after_discount'],
                'items' => $result['items'],
            ],
        ]);
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @param  array<int, mixed>|null  $brands
     * @return array<int, string>|null
     */
    private function normalizeBrands(?array $brands): ?array
    {
        if ($brands === null) {
            return null;
        }

        $normalized = array_values(array_filter(array_unique(array_map(
            static fn ($brand): string => trim((string) $brand),
            $brands,
        ))));

        return $normalized === [] ? null : $normalized;
    }

    private function duplicateCode(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Kode promo sudah dipakai',
            'errors' => ['code' => ['Kode promo sudah dipakai']],
        ], 422);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function invalidCode(string $message, ?array $data = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'errors' => ['code' => [$message]],
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, 422);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Kode promo tidak ditemukan',
        ], 404);
    }
}
