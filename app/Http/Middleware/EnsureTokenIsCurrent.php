<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\JWTGuard;

/**
 * Reject tokens revoked by a password change or reset (their "tv" claim is behind the user's token version).
 * Runs after auth:api, which has already verified the token's signature, expiry and blacklist. A user
 * authenticated without a token (actingAs in tests) is let through.
 */
class EnsureTokenIsCurrent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('api');
        $user = $request->user();

        if (! $guard instanceof JWTGuard || ! $user instanceof User) {
            return $next($request);
        }

        try {
            $tokenVersion = $guard->payload()->get('tv');
        } catch (JWTException) {
            return $next($request);
        }

        if ($user->tokenIsRevoked($tokenVersion)) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
