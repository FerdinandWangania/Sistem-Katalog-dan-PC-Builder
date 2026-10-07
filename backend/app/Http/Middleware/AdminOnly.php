<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user login bertipe admin. Dipakai setelah TokenAuth.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses khusus admin',
            ], 403);
        }

        return $next($request);
    }
}
