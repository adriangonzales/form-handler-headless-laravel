<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    /**
     * Exchange an email and password for an access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $token = $this->guard()->attempt($request->credentials());

        if (! is_string($token)) {
            $request->failed();
        }

        $request->succeeded();

        return $this->tokenResponse($token);
    }

    /**
     * Exchange a current or recently expired token for a new one.
     *
     * The old token is blacklisted. Refreshing is allowed until the
     * token is older than the configured refresh TTL, and never for a
     * token revoked by a password change or reset: the new token keeps
     * the original token version, so it is checked against revocation.
     *
     * @throws AuthenticationException
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = $this->guard()->refresh();
            $payload = $this->guard()->setToken($token)->payload();
        } catch (JWTException) {
            throw new AuthenticationException;
        }

        $subject = $payload->get('sub');
        $user = is_int($subject) || is_string($subject) ? User::find($subject) : null;

        if ($user === null || $user->tokenIsRevoked($payload->get('tv'))) {
            $this->guard()->invalidate();

            throw new AuthenticationException;
        }

        return $this->tokenResponse($token);
    }

    /**
     * Invalidate the current token.
     */
    public function logout(): Response
    {
        $this->guard()->logout();

        return response()->noContent();
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    private function guard(): JWTGuard
    {
        $guard = Auth::guard('api');

        if (! $guard instanceof JWTGuard) {
            throw new LogicException('The api guard must use the jwt driver.');
        }

        return $guard;
    }
}
