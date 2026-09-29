<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
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
     * token is older than the configured refresh TTL.
     *
     * @throws AuthenticationException
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = $this->guard()->refresh();
        } catch (JWTException) {
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
