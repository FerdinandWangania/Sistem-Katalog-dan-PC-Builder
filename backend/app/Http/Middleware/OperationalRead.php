<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baca data operasional: admin, superadmin, dan keuangan.
 * Dipakai hanya untuk endpoint GET (read-only).
 */
class OperationalRead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canReadOperational()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses baca operasional ditolak',
            ], 403);
        }

        return $next($request);
    }
}
