<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ikat user_id ke token: bila request membawa user_id, peminta wajib
 * login dan ID-nya harus sama (anti-spoofing cart/checkout orang lain).
 * Tanpa user_id (guest/session_id) request dilewatkan bebas.
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->input('user_id', $request->query('user_id'));

        if (!$userId) {
            return $next($request);
        }

        $user = TokenAuth::userFromHeader($request);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Wajib login untuk memakai user_id (header Authorization: Bearer <token>)',
            ], 401);
        }

        if ((string) $user->_id !== (string) $userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'user_id bukan milik akun Anda',
            ], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
