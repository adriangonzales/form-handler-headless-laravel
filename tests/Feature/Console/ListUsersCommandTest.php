<?php

namespace Tests\Feature\Console;

use App\Models\Form;
use App\Models\User;

it('lists every user', function (): void {
    $ada = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $grace = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    Form::factory()->for($ada)->create();

    $this->artisan('user:list')
        ->expectsTable(['ID', 'Name', 'Email', 'Forms', 'Created'], [
            [$ada->id, 'Ada Lovelace', 'ada@example.com', 1, $ada->created_at->toDateTimeString()],
            [$grace->id, 'Grace Hopper', 'grace@example.com', 0, $grace->created_at->toDateTimeString()],
        ])
        ->assertSuccessful();
});

it('says so when there are no users', function (): void {
    $this->artisan('user:list')
        ->expectsOutputToContain('There are no users.')
        ->assertSuccessful();
});
