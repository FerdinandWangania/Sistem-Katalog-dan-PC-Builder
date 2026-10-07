<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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
            'data'   => [
                'users' => [
                    'total'    => User::count(),
                    'admin'    => User::where('role', 'admin')->count(),
                    'customer' => User::where('role', 'customer')->count(),
                ],
                'products' => [
                    'total'     => Product::count(),
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
}
