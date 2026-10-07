<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth token Bearer untuk MongoDB (dengan expiry).
 * Header: Authorization: Bearer <token>
 */
class TokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = self::userFromHeader($request);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token tidak ditemukan, tidak valid, atau kedaluwarsa',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * Ambil user dari header Bearer, null bila hilang/invalid/kedaluwarsa.
     */
    public static function userFromHeader(Request $request): ?User
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $plain = substr($header, 7);
        if ($plain === '') {
            return null;
        }

        $user = User::where('api_token', hash('sha256', $plain))->first();

        if (!$user) {
            return null;
        }

        if ($user->token_expires_at && $user->token_expires_at->isPast()) {
            return null;
        }

        return $user;
    }
}
