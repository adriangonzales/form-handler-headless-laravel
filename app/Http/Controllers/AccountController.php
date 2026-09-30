<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\DeleteAccount;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Tymon\JWTAuth\JWTGuard;

class AccountController extends Controller
{
    /**
     * Update the authenticated user's name or email. Changing the email clears its verification.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user);
    }

    /**
     * Change the password, revoke every existing token, and return a new one for this client.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);
        $user->revokeTokens();

        $guard = $this->guard();
        $guard->logout();

        return response()->json([
            'access_token' => $guard->login($user),
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Permanently delete the account and everything it owns.
     */
    public function destroy(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->guard()->logout();
        $deleteAccount($user);

        return response()->noContent();
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
