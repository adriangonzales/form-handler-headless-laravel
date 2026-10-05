<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('updates the password and revokes existing tokens', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $tokenVersion = $user->token_version;

    $this->artisan('user:password', [
        'email' => 'ADA@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertSuccessful();

    $user->refresh();

    expect(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue()
        ->and($user->tokenIsRevoked($tokenVersion))->toBeTrue();
});

it('prompts for the password when not given as an option', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->artisan('user:password', ['email' => 'ada@example.com'])
        ->expectsQuestion('New password', 'correct-horse-battery-staple')
        ->assertSuccessful();

    expect(Hash::check('correct-horse-battery-staple', $user->refresh()->password))->toBeTrue();
});

it('fails for an unknown email', function (): void {
    $this->artisan('user:password', [
        'email' => 'nobody@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])
        ->expectsOutputToContain('No user found with email [nobody@example.com].')
        ->assertFailed();
});

it('refuses a password that fails validation', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $originalHash = $user->password;

    $this->artisan('user:password', [
        'email' => 'ada@example.com',
        '--password' => 'short',
    ])->assertFailed();

    $user->refresh();

    expect($user->password)->toBe($originalHash)
        ->and($user->token_version)->toBe(0);
});
