<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Middleware\TokenAuth;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Identitas pemilik data: user login (Bearer) atau tamu (session_id).
 */
trait ResolvesIdentity
{
    /**
     * @return array{user_id: string|null, session_id: string|null, user: User|null}|null
     */
    protected function resolveIdentity(Request $request): ?array
    {
        $user = $request->user() ?? TokenAuth::userFromHeader($request);

        if ($user) {
            return [
                'user_id' => (string) $user->_id,
                'session_id' => null,
                'user' => $user,
            ];
        }

        $sessionId = trim((string) $request->input('session_id', $request->query('session_id', '')));

        if ($sessionId === '') {
            return null;
        }

        return [
            'user_id' => null,
            'session_id' => $sessionId,
            'user' => null,
        ];
    }

    /**
     * @param  array{user_id: string|null, session_id: string|null, user: User|null}  $identity
     */
    protected function identityQuery(array $identity): array
    {
        return $identity['user_id'] !== null
            ? ['user_id' => $identity['user_id']]
            : ['session_id' => $identity['session_id']];
    }
}
