<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user from options', function (): void {
    $this->artisan('user:create', [
        '--name' => 'Ada Lovelace',
        '--email' => 'Ada@Example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertSuccessful();

    $user = User::sole();

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBe('ada@example.com')
        ->and(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue();
});

it('prompts for anything not given as an option', function (): void {
    $this->artisan('user:create', ['--email' => 'ada@example.com'])
        ->expectsQuestion('Name', 'Ada Lovelace')
        ->expectsQuestion('Password', 'correct-horse-battery-staple')
        ->assertSuccessful();

    expect(User::sole()->name)->toBe('Ada Lovelace');
});

it('refuses an email that is already registered', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->artisan('user:create', [
        '--name' => 'Ada Lovelace',
        '--email' => 'ADA@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertFailed();

    expect(User::count())->toBe(1);
});
