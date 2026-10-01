<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\DeniedToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);
});

function loginToken(): string
{
    $token = test()->postJson(route('auth.login'), [
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ])->json('access_token');

    freshAuthState();

    return $token;
}

it('issues a bearer token for valid credentials', function (): void {
    $response = $this->postJson(route('auth.login'), [
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertOk();
    $response->assertJson([
        'token_type' => 'bearer',
        'expires_in' => 3600,
    ]);
    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
});

it('treats the email as case-insensitive', function (): void {
    $response = $this->postJson(route('auth.login'), [
        'email' => 'Owner@Example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertOk();
});

it('rejects invalid credentials', function (array $credentials, string $errorKey): void {
    $response = $this->postJson(route('auth.login'), $credentials);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'wrong password' => [['email' => 'owner@example.com', 'password' => 'wrong'], 'email'],
    'unknown email' => [['email' => 'nobody@example.com', 'password' => 'correct-horse-battery-staple'], 'email'],
    'missing password' => [['email' => 'owner@example.com'], 'password'],
    'malformed email' => [['email' => 'not-an-email', 'password' => 'x'], 'email'],
]);

it('throttles repeated failed logins', function (): void {
    foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
        $this->postJson(route('auth.login'), [
            'email' => 'owner@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $response = $this->postJson(route('auth.login'), [
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertTooManyRequests();
    $response->assertJsonValidationErrors('email');
});

it('clears failed attempts after a successful login', function (): void {
    foreach (range(1, LoginRequest::MAX_ATTEMPTS - 1) as $attempt) {
        $this->postJson(route('auth.login'), ['email' => 'owner@example.com', 'password' => 'wrong']);
    }

    loginToken();

    foreach (range(1, LoginRequest::MAX_ATTEMPTS - 1) as $attempt) {
        $this->postJson(route('auth.login'), ['email' => 'owner@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }
});

it('returns the authenticated user', function (): void {
    $token = loginToken();

    $response = $this->withToken($token)->getJson(route('auth.me'));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => 'owner@example.com',
        ],
    ]);
    $response->assertJsonMissingPath('data.password');
});

it('authenticates API requests with the token', function (): void {
    $token = loginToken();

    $this->withToken($token)->getJson(route('forms.index'))->assertOk();
});

it('rejects requests without a valid token', function (?string $token): void {
    $request = $token === null ? $this : $this->withToken($token);

    $response = $request->getJson(route('auth.me'));

    $response->assertUnauthorized();
    $response->assertExactJson(['message' => 'Unauthenticated.']);
})->with([
    'no token' => [null],
    'garbage token' => ['not-a-jwt'],
]);

it('invalidates the token on logout', function (): void {
    $token = loginToken();

    $this->withToken($token)->postJson(route('auth.logout'))->assertNoContent();
    freshAuthState();

    $this->withToken($token)->getJson(route('auth.me'))->assertUnauthorized();
});

it('keeps a logged out token invalid after the cache is cleared', function (): void {
    $token = loginToken();

    $this->withToken($token)->postJson(route('auth.logout'))->assertNoContent();
    freshAuthState();
    Cache::flush();

    $this->withToken($token)->getJson(route('auth.me'))->assertUnauthorized();
    expect(DeniedToken::query()->sole()->expires_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('keeps denied tokens until the refresh window ends, then prunes them', function (): void {
    $this->freezeSecond();
    $token = loginToken();

    $this->withToken($token)->postJson(route('auth.logout'))->assertNoContent();

    $denied = DeniedToken::query()->sole();
    expect($denied->expires_at->equalTo(now()->addMinutes(config('jwt.refresh_ttl') + 1)))->toBeTrue();

    $this->travelTo($denied->expires_at->subSecond());
    $this->artisan('model:prune', ['--model' => [DeniedToken::class]])->assertSuccessful();
    expect(DeniedToken::query()->count())->toBe(1);

    $this->travelTo($denied->expires_at);
    $this->artisan('model:prune', ['--model' => [DeniedToken::class]])->assertSuccessful();
    expect(DeniedToken::query()->count())->toBe(0);
});

it('refreshes a token and invalidates the old one', function (): void {
    $token = loginToken();

    $response = $this->withToken($token)->postJson(route('auth.refresh'));

    $response->assertOk();

    $newToken = $response->json('access_token');
    expect($newToken)->toBeString()->not->toBe($token);
    freshAuthState();

    $this->withToken($token)->getJson(route('auth.me'))->assertUnauthorized();
    freshAuthState();

    $this->withToken($newToken)->getJson(route('auth.me'))->assertOk();
});

it('refreshes an expired token within the refresh window', function (): void {
    $token = loginToken();

    $this->travel(61)->minutes();

    $this->withToken($token)->getJson(route('auth.me'))->assertUnauthorized();
    freshAuthState();

    $this->withToken($token)->postJson(route('auth.refresh'))->assertOk();
});

it('rejects refresh without a token', function (): void {
    $response = $this->postJson(route('auth.refresh'));

    $response->assertUnauthorized();
    $response->assertExactJson(['message' => 'Unauthenticated.']);
});

it('measures the refresh window from the original login', function (): void {
    $token = loginToken();

    $this->travel(6)->days();
    $refreshed = $this->withToken($token)->postJson(route('auth.refresh'))
        ->assertOk()
        ->json('access_token');
    freshAuthState();

    $this->travel(2)->days();
    $this->withToken($refreshed)->postJson(route('auth.refresh'))->assertUnauthorized();
});

it('rejects refresh once the token is older than the 7-day refresh window', function (): void {
    $this->travelTo('2026-01-01 00:00:00');
    $token = loginToken();

    $this->travelTo('2026-01-08 00:00:01');
    $response = $this->withToken($token)->postJson(route('auth.refresh'));

    $response->assertUnauthorized();
    $response->assertExactJson(['message' => 'Unauthenticated.']);
});
