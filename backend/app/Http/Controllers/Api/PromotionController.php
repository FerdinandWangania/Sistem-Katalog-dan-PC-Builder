<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * List promo yang sedang aktif (now di antara start_date & end_date).
     * GET /api/promotions
     */
    public function index(): JsonResponse
    {
        $now = now();
        $promotions = Promotion::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->get();

        return response()->json([
            'status' => 'success',
            'count'  => $promotions->count(),
            'data'   => $promotions,
        ]);
    }

    /**
     * Buat promo (admin).
     * POST /api/promotions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'brands'              => 'required|array|min:1',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after:start_date',
        ]);

        $promo = Promotion::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Promo berhasil dibuat',
            'data'    => $promo,
        ], 201);
    }

    /**
     * Ubah promo (admin).
     * PUT /api/promotions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $promo = Promotion::find($id);
        if (!$promo) {
            return response()->json(['status' => 'error', 'message' => 'Promo tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'title'               => 'sometimes|string|max:255',
            'brands'              => 'sometimes|array|min:1',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'start_date'          => 'sometimes|date',
            'end_date'            => 'sometimes|date|after:start_date',
        ]);

        $promo->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Promo berhasil diperbarui',
            'data'    => $promo->fresh(),
        ]);
    }

    /**
     * Hapus promo (admin).
     * DELETE /api/promotions/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $promo = Promotion::find($id);
        if (!$promo) {
            return response()->json(['status' => 'error', 'message' => 'Promo tidak ditemukan'], 404);
        }

        $promo->delete();

        return response()->json(['status' => 'success', 'message' => 'Promo berhasil dihapus']);
    }
}
