<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the app while the user signed in with a temporary password and has
 * not chosen a new one yet. Only auth/me, auth/logout and
 * auth/change-temporary-password stay reachable (they are outside this middleware).
 */
class EnsurePasswordChanged
{
    public const CODE = 'password_change_required';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return response()->json([
                'message' => 'Defina uma nova senha para continuar.',
                'code' => self::CODE,
            ], 403);
        }

        return $next($request);
    }
}
