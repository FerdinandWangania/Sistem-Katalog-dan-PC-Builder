<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya superadmin (Manajemen Akun & perubahan role).
 * Dipakai setelah TokenAuth.
 */
class SuperadminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperadmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses khusus superadmin',
            ], 403);
        }

        return $next($request);
    }
}
