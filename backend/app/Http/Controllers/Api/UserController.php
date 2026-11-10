<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * UserController — daftar akun (admin/keuangan read-only) dan
 * Manajemen Akun (superadmin): tambah, ubah, hapus, ubah role.
 */
class UserController extends Controller
{
    private const READ_COLUMNS = [
        '_id', 'name', 'email', 'phone', 'role', 'must_change_password', 'created_at',
    ];

    /**
     * List akun. Bisa dibaca admin, superadmin, dan keuangan (read-only).
     * GET /api/users?role=customer
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $users = $query->select(self::READ_COLUMNS)->get();

        return response()->json([
            'status' => 'success',
            'count' => $users->count(),
            'data' => $users,
        ]);
    }

    /**
     * Detail akun.
     * GET /api/users/{id}
     */
    public function show(string $id): JsonResponse
    {
        $user = User::select(self::READ_COLUMNS)->find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * Tambah akun (superadmin). Password boleh dikosongkan -> dibuat otomatis.
     * POST /api/admin/users
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|string|in:'.implode(',', User::ROLES),
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email sudah terdaftar',
            ], 422);
        }

        $generated = blank($validated['password'] ?? null);
        $plainPassword = $generated ? Str::password(12, true, true, false) : $validated['password'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $plainPassword,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        $data = ['user' => $user->fresh()];
        if ($generated) {
            $data['temporary_password'] = $plainPassword; // ditampilkan sekali saja
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Akun berhasil dibuat'.($generated ? '. Simpan password sementara, hanya tampil sekali.' : '.'),
            'data' => $data,
        ], 201);
    }

    /**
     * Ubah akun (superadmin).
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:30',
            'role' => 'sometimes|string|in:'.implode(',', User::ROLES),
        ]);

        if (isset($validated['email'])) {
            $emailTaken = User::where('email', $validated['email'])
                ->where('_id', '!=', $user->_id)
                ->exists();

            if ($emailTaken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email sudah dipakai akun lain',
                ], 422);
            }
        }

        $newRole = $validated['role'] ?? $user->role;

        if ($this->isSelf($request, $user) && $newRole !== $user->role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak bisa mengubah role diri sendiri',
            ], 422);
        }

        if ($this->wouldRemoveLastSuperadmin($user, $newRole)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minimal harus ada 1 superadmin',
            ], 422);
        }

        $temporaryPassword = null;

        if (array_key_exists('password', $validated) && filled($validated['password'])) {
            $temporaryPassword = $validated['password'];
            $user->password = $temporaryPassword;
            $user->must_change_password = true;
        }

        $user->fill(array_diff_key($validated, ['password' => null]));
        $user->save();

        $data = ['user' => $user->fresh()];

        if ($temporaryPassword !== null) {
            $data['temporary_password'] = $temporaryPassword;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Akun berhasil diperbarui',
            'data' => $data,
        ]);
    }

    /**
     * Hapus akun (superadmin). Diri sendiri & superadmin terakhir ditolak.
     * DELETE /api/admin/users/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($this->isSelf($request, $user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak bisa menghapus akun sendiri',
            ], 422);
        }

        if ($user->isSuperadmin() && $this->countSuperadmins() <= 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Superadmin terakhir tidak boleh dihapus',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Akun berhasil dihapus',
        ]);
    }

    /**
     * Ubah role akun (superadmin).
     * PATCH /api/users/{id}/role  Body: { "role": "admin" }
     */
    public function setRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:'.implode(',', User::ROLES),
        ]);

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($this->isSelf($request, $user) && $validated['role'] !== $user->role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak bisa mengubah role diri sendiri',
            ], 422);
        }

        if ($this->wouldRemoveLastSuperadmin($user, $validated['role'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minimal harus ada 1 superadmin',
            ], 422);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'status' => 'success',
            'message' => "Role {$user->email} diubah menjadi {$validated['role']}",
            'data' => $user->fresh(),
        ]);
    }

    private function isSelf(Request $request, User $user): bool
    {
        $actor = $request->user();

        return $actor && (string) $actor->_id === (string) $user->_id;
    }

    private function wouldRemoveLastSuperadmin(User $user, string $newRole): bool
    {
        return $user->isSuperadmin()
            && $newRole !== 'superadmin'
            && $this->countSuperadmins() <= 1;
    }

    private function countSuperadmins(): int
    {
        return User::where('role', 'superadmin')->count();
    }
}
