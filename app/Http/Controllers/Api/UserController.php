<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List user (Admin / Customer).
     * GET /api/users?role=customer
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $users = $query->select(['_id', 'name', 'email', 'role', 'phone', 'address', 'created_at'])->get();

        return response()->json([
            'status' => 'success',
            'count'  => $users->count(),
            'data'   => $users,
        ]);
    }

    /**
     * Detail user.
     * GET /api/users/{id}
     */
    public function show(string $id): JsonResponse
    {
        $user = User::select(['_id', 'name', 'email', 'role', 'phone', 'address', 'created_at'])->find($id);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $user,
        ]);
    }
}
