<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;

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
}
