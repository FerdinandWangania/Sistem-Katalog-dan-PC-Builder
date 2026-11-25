<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesIdentity;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ResolvesIdentity;

    private const MAX_ADDRESSES = 10;

    /**
     * Daftar alamat milik user/session.
     * GET /api/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $addresses = Address::where($this->identityQuery($identity))
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $addresses->count(),
            'data' => $addresses,
        ]);
    }

    /**
     * Tambah alamat (maksimal 10 per pemilik).
     * POST /api/addresses
     */
    public function store(Request $request): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $ownedAddresses = Address::where($this->identityQuery($identity));
        $count = (clone $ownedAddresses)->count();

        if ($count >= self::MAX_ADDRESSES) {
            return response()->json([
                'status' => 'error',
                'message' => 'Maksimal '.self::MAX_ADDRESSES.' alamat per akun',
                'errors' => ['address' => ['Maksimal '.self::MAX_ADDRESSES.' alamat per akun']],
            ], 422);
        }

        $isDefault = (bool) ($validated['is_default'] ?? false) || $count === 0;

        if ($isDefault) {
            Address::where($this->identityQuery($identity))->update(['is_default' => false]);
        }

        $address = Address::create([
            ...$validated,
            ...$this->identityQuery($identity),
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alamat berhasil ditambahkan',
            'data' => $address,
        ], 201);
    }

    /**
     * Ubah alamat milik sendiri.
     * PUT /api/addresses/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $address = $this->findOwnedAddress($identity, $id);

        if (! $address) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:50'],
            'recipient' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (($validated['is_default'] ?? false) === true) {
            Address::where($this->identityQuery($identity))->update(['is_default' => false]);
        }

        $wasDefault = (bool) $address->is_default;
        $address->update($validated);

        if ($wasDefault && ($validated['is_default'] ?? true) === false) {
            $this->ensureDefault($identity, (string) $address->_id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Alamat berhasil diperbarui',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * Hapus alamat milik sendiri; alamat utama lain dipromosikan otomatis.
     * DELETE /api/addresses/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $identity = $this->resolveIdentity($request);

        if (! $identity) {
            return $this->identityRequired();
        }

        $address = $this->findOwnedAddress($identity, $id);

        if (! $address) {
            return $this->notFound();
        }

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $this->ensureDefault($identity);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Alamat berhasil dihapus',
        ]);
    }

    /**
     * @param  array{user_id: string|null, session_id: string|null, user: User|null}  $identity
     */
    private function findOwnedAddress(array $identity, string $id): ?Address
    {
        return Address::where('_id', $id)
            ->where($this->identityQuery($identity))
            ->first();
    }

    /**
     * Pastikan selalu ada satu alamat utama.
     *
     * @param  array{user_id: string|null, session_id: string|null, user: User|null}  $identity
     */
    private function ensureDefault(array $identity, ?string $excludeId = null): void
    {
        $query = Address::where($this->identityQuery($identity));

        if ($excludeId !== null) {
            $query->where('_id', '!=', $excludeId);
        }

        if ((clone $query)->where('is_default', true)->exists()) {
            return;
        }

        $next = $query->orderBy('created_at')->first();

        if ($next) {
            $next->update(['is_default' => true]);
        }
    }

    private function identityRequired(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Wajib login atau sertakan session_id',
            'errors' => ['session_id' => ['Wajib login atau sertakan session_id']],
        ], 422);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Alamat tidak ditemukan',
        ], 404);
    }
}
