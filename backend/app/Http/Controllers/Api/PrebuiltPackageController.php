<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrebuiltPackage;
use Illuminate\Http\JsonResponse;

class PrebuiltPackageController extends Controller
{
    /**
     * List semua paket PC rakitan preset beserta komponennya.
     * GET /api/packages
     */
    public function index(): JsonResponse
    {
        $packages = PrebuiltPackage::with('items.product')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $packages->count(),
            'data'   => $packages,
        ]);
    }

    /**
     * Detail paket PC rakitan.
     * GET /api/packages/{id}
     */
    public function show(string $id): JsonResponse
    {
        $package = PrebuiltPackage::with('items.product')->find($id);

        if (!$package) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket PC tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $package,
        ]);
    }
}
