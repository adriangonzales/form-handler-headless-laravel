<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Form;
use App\Models\FormEntry;
use App\Models\FormEntryExport;
use App\Models\FormNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);
    $this->token = apiToken($this->user);
});

it('updates the profile name', function (): void {
    $response = $this->withToken($this->token)->patchJson(route('auth.me.update'), ['name' => 'Ada Lovelace']);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Ada Lovelace');
    $response->assertJsonPath('data.email', 'owner@example.com');
    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('lowercases a changed email and clears its verification', function (): void {
    $response = $this->withToken($this->token)->patchJson(route('auth.me.update'), ['email' => 'Ada@Example.com']);

    $response->assertOk();
    $response->assertJsonPath('data.email', 'ada@example.com');
    $response->assertJsonPath('data.email_verified_at', null);
});

it('keeps the verification when the same email is resubmitted', function (): void {
    $response = $this->withToken($this->token)->patchJson(route('auth.me.update'), ['email' => 'Owner@Example.com']);

    $response->assertOk();
    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an email used by another account', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->withToken($this->token)->patchJson(route('auth.me.update'), ['email' => 'Taken@example.com']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

it('changes the password, revokes existing tokens and issues a new one', function (): void {
    $otherDeviceToken = apiToken($this->user);

    $response = $this->withToken($this->token)->putJson(route('auth.password.update'), [
        'current_password' => 'correct-horse-battery-staple',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    expect(Hash::check('a-brand-new-passphrase', $this->user->fresh()->password))->toBeTrue();
    $newToken = $response->json('access_token');

    foreach ([$this->token, $otherDeviceToken] as $revoked) {
        freshAuthState();
        $this->withToken($revoked)->getJson(route('auth.me'))->assertUnauthorized();
    }

    freshAuthState();
    $this->withToken($newToken)->getJson(route('auth.me'))->assertOk();
});

it('does not let a revoked token be refreshed', function (): void {
    $otherDeviceToken = apiToken($this->user);

    $this->withToken($this->token)->putJson(route('auth.password.update'), [
        'current_password' => 'correct-horse-battery-staple',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertOk();
    freshAuthState();

    $this->withToken($otherDeviceToken)->postJson(route('auth.refresh'))->assertUnauthorized();
});

it('keeps a token valid across refreshes when nothing was revoked', function (): void {
    $refreshed = $this->withToken($this->token)->postJson(route('auth.refresh'))->assertOk()->json('access_token');
    freshAuthState();

    $this->withToken($refreshed)->getJson(route('auth.me'))->assertOk();
});

it('rejects an invalid password change', function (array $payload, string $errorKey): void {
    $response = $this->withToken($this->token)->putJson(route('auth.password.update'), $payload);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
    expect(Hash::check('correct-horse-battery-staple', $this->user->fresh()->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'wrong', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'], 'current_password'],
    'unconfirmed' => [['current_password' => 'correct-horse-battery-staple', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'different'], 'password'],
    'unchanged' => [['current_password' => 'correct-horse-battery-staple', 'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple'], 'password'],
]);

it('deletes the account and everything it owns', function (): void {
    Storage::fake('local');

    $form = Form::factory()->create(['user_id' => $this->user->id]);
    $deletedForm = Form::factory()->create(['user_id' => $this->user->id]);
    $deletedForm->delete();
    $entry = FormEntry::factory()->create(['form_id' => $form->id]);
    $deletedEntry = FormEntry::factory()->create(['form_id' => $deletedForm->id]);
    $deletedEntry->delete();
    $notification = FormNotification::factory()->create(['form_id' => $form->id]);
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $form->id]);
    Storage::disk('local')->put($export->path, 'id');

    $otherForm = Form::factory()->create();
    $otherEntry = FormEntry::factory()->create(['form_id' => $otherForm->id]);

    $response = $this->withToken($this->token)->deleteJson(route('auth.me.destroy'), [
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertNoContent();
    foreach ([$this->user, $form, $deletedForm, $entry, $deletedEntry, $notification, $export] as $model) {
        $this->assertModelMissing($model);
    }
    Storage::disk('local')->assertMissing($export->path);
    $this->assertModelExists($otherForm);
    $this->assertModelExists($otherEntry);

    freshAuthState();
    $this->withToken($this->token)->getJson(route('auth.me'))->assertUnauthorized();
});

it('requires the current password to delete the account', function (): void {
    $response = $this->withToken($this->token)->deleteJson(route('auth.me.destroy'), ['password' => 'wrong']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
    $this->assertModelExists($this->user);
});

it('requires authentication for account management', function (string $method, string $routeName): void {
    $this->json($method, route($routeName))->assertUnauthorized();
})->with([
    'update profile' => ['PATCH', 'auth.me.update'],
    'change password' => ['PUT', 'auth.password.update'],
    'delete account' => ['DELETE', 'auth.me.destroy'],
]);
