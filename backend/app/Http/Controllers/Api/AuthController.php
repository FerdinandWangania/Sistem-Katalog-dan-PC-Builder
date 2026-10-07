<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AuthController — register / login / logout / profil (token Bearer).
 * Token yang disimpan di DB adalah hash SHA256; plain token hanya
 * dikembalikan sekali saat register/login (ala Sanctum).
 */
class AuthController extends Controller
{
    /**
     * Daftar akun customer baru.
     * POST /api/register  Body: { name, email, password, phone? }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string|max:30',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email sudah terdaftar',
            ], 422);
        }

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone'] ?? null,
            'role'     => 'customer', // role dikunci, tidak bisa daftar jadi admin
        ]);

        // Token verifikasi email (dikirim via email di produksi; saat ini via log + debug response)
        $verifyPlain = Str::random(40);
        $user->update(['verify_token' => hash('sha256', $verifyPlain)]);
        Log::info('Verify email token untuk ' . $user->email . ': ' . $verifyPlain);

        $plainToken = $this->issueToken($user);

        $data = [
            'user'  => $user->fresh(),
            'token' => $plainToken,
        ];
        if (config('app.debug')) {
            $data['verify_token'] = $verifyPlain; // DEV ONLY: cabut saat email aktif
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Registrasi berhasil. Cek email untuk verifikasi.',
            'data'    => $data,
        ], 201);
    }

    /**
     * Login customer / admin.
     * POST /api/login  Body: { email, password }
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email atau password salah',
            ], 401);
        }

        $plainToken = $this->issueToken($user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Login berhasil',
            'data'    => [
                'user'  => $user->fresh(),
                'token' => $plainToken,
            ],
        ]);
    }

    /**
     * Login / daftar via Google (ID token dari Google Identity Services).
     * POST /api/login/google  Body: { id_token }
     */
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validated = $request->validate(['id_token' => 'required|string']);

        $clientId = config('services.google.client_id');
        if (!$clientId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'GOOGLE_CLIENT_ID belum dikonfigurasi di server',
            ], 500);
        }

        try {
            $client = new \Google\Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($validated['id_token']);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token Google tidak valid',
            ], 401);
        }

        if (!$payload || empty($payload['email']) || ($payload['email_verified'] ?? false) !== true) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token Google tidak valid atau email belum diverifikasi Google',
            ], 401);
        }

        // User lama by google_id, fallback by email (tautkan akun yang sudah ada)
        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $payload['email'])->first();

        if ($user) {
            $user->update([
                'google_id'         => $payload['sub'],
                'avatar'            => $payload['picture'] ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name'              => $payload['name'] ?? $payload['email'],
                'email'             => $payload['email'],
                'password'          => Hash::make(Str::random(40)), // tidak dipakai, login via Google
                'role'              => 'customer',
                'google_id'         => $payload['sub'],
                'avatar'            => $payload['picture'] ?? null,
                'email_verified_at' => now(), // Google sudah verifikasi email
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Login Google berhasil',
            'data'    => [
                'user'  => $user->fresh(),
                'token' => $this->issueToken($user),
            ],
        ]);
    }

    /**
     * Logout — cabut token aktif.
     * POST /api/logout (auth)
     */
    public function logout(Request $request): JsonResponse
    {
        // Hapus field (bukan null) agar index sparse-unique tidak bentrok
        $user = $request->user();
        unset($user->api_token, $user->token_expires_at);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil',
        ]);
    }

    /**
     * Profil user dari token aktif.
     * GET /api/me (auth)
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $request->user(),
        ]);
    }

    /**
     * Terbitkan token baru (rotate: token lama otomatis hangus).
     * Berlaku 30 hari.
     */
    private function issueToken(User $user): string
    {
        $plain = Str::random(60);
        $user->update([
            'api_token'        => hash('sha256', $plain),
            'token_expires_at' => now()->addDays(30),
        ]);

        return $plain;
    }

    /**
     * Minta token reset password.
     * POST /api/forgot-password  Body: { email }
     * Selalu 200 agar tidak bocor daftar email terdaftar.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $validated['email'])->first();
        if ($user) {
            $plain = Str::random(40);
            $user->update([
                'reset_token'      => hash('sha256', $plain),
                'reset_expires_at' => now()->addHour(),
            ]);
            Log::info('Reset password token untuk ' . $user->email . ': ' . $plain);
        }

        $data = [];
        if (config('app.debug') && isset($plain)) {
            $data['reset_token'] = $plain; // DEV ONLY: cabut saat email aktif
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Jika email terdaftar, token reset telah dikirim',
            'data'    => $data,
        ]);
    }

    /**
     * Reset password pakai token.
     * POST /api/reset-password  Body: { email, token, password }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->reset_token
            || !hash_equals($user->reset_token, hash('sha256', $validated['token']))
            || !$user->reset_expires_at || $user->reset_expires_at->isPast()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token reset tidak valid atau kedaluwarsa',
            ], 422);
        }

        $user->update([
            'password'         => Hash::make($validated['password']),
            'reset_token'      => null,
            'reset_expires_at' => null,
        ]);
        // Paksa logout semua sesi: hapus field token (bukan null, index unique)
        unset($user->api_token, $user->token_expires_at);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password berhasil direset, silakan login ulang',
        ]);
    }

    /**
     * Verifikasi email pakai token.
     * POST /api/verify-email  Body: { email, token }
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->verify_token
            || !hash_equals($user->verify_token, hash('sha256', $validated['token']))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token verifikasi tidak valid',
            ], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'verify_token'      => null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Email berhasil diverifikasi',
        ]);
    }

    /**
     * Kirim ulang token verifikasi (wajib login).
     * POST /api/resend-verification (auth)
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Email sudah terverifikasi',
            ]);
        }

        $plain = Str::random(40);
        $user->update(['verify_token' => hash('sha256', $plain)]);
        Log::info('Verify email token untuk ' . $user->email . ': ' . $plain);

        $data = [];
        if (config('app.debug')) {
            $data['verify_token'] = $plain; // DEV ONLY
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Token verifikasi dikirim ulang',
            'data'    => $data,
        ]);
    }
}
