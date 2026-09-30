<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);
});

it('emails a reset link pointing at the client application', function (): void {
    Notification::fake();
    config(['auth.passwords.users.reset_url' => 'https://app.example.com/reset-password']);

    $response = $this->postJson(route('auth.forgot-password'), ['email' => 'Owner@Example.com']);

    $response->assertOk();
    $response->assertExactJson(['message' => 'If an account exists for that email, a password reset link has been sent.']);
    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification): bool {
        $url = $notification->toMail($this->user)->actionUrl;

        return $url === 'https://app.example.com/reset-password?'.http_build_query([
            'token' => $notification->token,
            'email' => 'owner@example.com',
        ]);
    });
});

it('gives the same response for an unknown email without sending anything', function (): void {
    Notification::fake();

    $response = $this->postJson(route('auth.forgot-password'), ['email' => 'nobody@example.com']);

    $response->assertOk();
    $response->assertExactJson(['message' => 'If an account exists for that email, a password reset link has been sent.']);
    Notification::assertNothingSent();
});

it('resets the password with a valid token and revokes existing tokens', function (): void {
    $existingToken = apiToken($this->user);
    $resetToken = Password::broker()->createToken($this->user);

    $response = $this->postJson(route('auth.reset-password'), [
        'token' => $resetToken,
        'email' => 'Owner@Example.com',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ]);

    $response->assertOk();
    $response->assertExactJson(['message' => 'Your password has been reset.']);
    expect(Hash::check('a-brand-new-passphrase', $this->user->fresh()->password))->toBeTrue();

    freshAuthState();
    $this->withToken($existingToken)->getJson(route('auth.me'))->assertUnauthorized();

    freshAuthState();
    $this->postJson(route('auth.login'), ['email' => 'owner@example.com', 'password' => 'a-brand-new-passphrase'])->assertOk();
});

it('rejects a reset with a bad token or unknown email using the same message', function (string $email, bool $validToken): void {
    $token = $validToken ? Password::broker()->createToken($this->user) : 'not-a-real-token';

    $response = $this->postJson(route('auth.reset-password'), [
        'token' => $token,
        'email' => $email,
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.email', ['This password reset token is invalid.']);
    expect(Hash::check('correct-horse-battery-staple', $this->user->fresh()->password))->toBeTrue();
})->with([
    'bad token' => ['owner@example.com', false],
    'unknown email' => ['nobody@example.com', true],
]);

it('rejects a reset whose new password is not confirmed', function (): void {
    $response = $this->postJson(route('auth.reset-password'), [
        'token' => Password::broker()->createToken($this->user),
        'email' => 'owner@example.com',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'different',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
});

it('throttles password reset requests', function (): void {
    Notification::fake();

    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('auth.forgot-password'), ['email' => 'nobody@example.com'])->assertOk();
    }

    $this->postJson(route('auth.forgot-password'), ['email' => 'nobody@example.com'])->assertTooManyRequests();
});
