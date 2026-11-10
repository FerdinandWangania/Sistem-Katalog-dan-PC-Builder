<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan keuangan: role keuangan dan superadmin.
 * Admin operasional tidak otomatis boleh membuka laporan keuangan.
 */
class FinanceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isFinance()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses khusus keuangan',
            ], 403);
        }

        return $next($request);
    }
}
