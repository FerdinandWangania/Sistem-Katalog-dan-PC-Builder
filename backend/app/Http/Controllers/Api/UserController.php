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

    /**
     * Ubah role user (admin only).
     * PATCH /api/users/{id}/role  Body: { "role": "admin" | "customer" }
     */
    public function setRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:admin,customer',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        // Tidak boleh cabut admin diri sendiri
        if ((string) $request->user()->_id === (string) $user->_id && $validated['role'] !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak bisa mencabut admin diri sendiri',
            ], 422);
        }

        // Jaga minimal 1 admin tersisa
        if ($user->isAdmin() && $validated['role'] !== 'admin'
            && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Minimal harus ada 1 admin',
            ], 422);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'status'  => 'success',
            'message' => "Role {$user->email} diubah menjadi {$validated['role']}",
            'data'    => $user->fresh(),
        ]);
    }
}
