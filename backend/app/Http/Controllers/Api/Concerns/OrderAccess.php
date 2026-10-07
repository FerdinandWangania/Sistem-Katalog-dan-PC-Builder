<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Middleware\TokenAuth;

/**
 * Kepemilikan pesanan: admin bebas, user hanya miliknya,
 * guest hanya via session_id yang cocok.
 */
trait OrderAccess
{
    /**
     * @return array{0: \App\Models\User|null, 1: string|null}
     */
    private function accessIdentity($request): array
    {
        $user = TokenAuth::userFromHeader($request);
        $sessionId = $request->input('session_id', $request->query('session_id'));

        return [$user, $sessionId ?: null];
    }

    private function canAccessOrder($request, $order): bool
    {
        [$user, $sessionId] = $this->accessIdentity($request);

        if ($user && $user->isAdmin()) {
            return true;
        }
        if ($user && $order->user_id && (string) $order->user_id === (string) $user->_id) {
            return true;
        }
        if ($sessionId && $order->session_id && $order->session_id === $sessionId) {
            return true;
        }

        return false;
    }
}
