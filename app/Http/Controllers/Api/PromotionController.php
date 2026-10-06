<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    /**
     * List promo aktif.
     * GET /api/promotions
     */
    public function index(): JsonResponse
    {
        $promotions = Promotion::all();

        return response()->json([
            'status' => 'success',
            'count'  => $promotions->count(),
            'data'   => $promotions,
        ]);
    }
}
