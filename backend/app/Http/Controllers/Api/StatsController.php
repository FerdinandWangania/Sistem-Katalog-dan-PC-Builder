<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * StatsController — ringkasan dashboard admin.
 * GET /api/admin/stats (admin)
 */
class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $paidStatuses = ['processing', 'shipped', 'delivered'];

        $ordersByStatus = [];
        foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status) {
            $ordersByStatus[$status] = Order::where('status', $status)->count();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'admin' => User::whereIn('role', ['admin', 'superadmin'])->count(),
                    'customer' => User::where('role', 'customer')->count(),
                    'keuangan' => User::where('role', 'keuangan')->count(),
                ],
                'products' => [
                    'total' => Product::count(),
                    'low_stock' => Product::where('stock', '<', 5)->get(['_id', 'name', 'stock'])->values(),
                ],
                'orders' => array_merge(
                    ['total' => Order::count()],
                    $ordersByStatus,
                ),
                'revenue' => (float) Order::whereIn('status', $paidStatuses)->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Produk terlaris pada periode (admin/keuangan).
     * GET /api/admin/top-products?from=2026-11-01&to=2026-11-30&limit=10
     */
    public function topProducts(Request $request, FinanceService $financeService): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        if ($to->lt($from)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanggal akhir harus setelah tanggal mulai',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => $financeService->topProducts($from, $to, (int) ($validated['limit'] ?? 10)),
        ]);
    }
}
