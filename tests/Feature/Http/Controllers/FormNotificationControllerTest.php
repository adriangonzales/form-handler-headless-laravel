<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Form;
use App\Models\FormNotification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->form = Form::factory()->create(['user_id' => $this->user->id]);
});

it('requires authentication to view the form entry index', function (): void {
    $response = $this->get(route('forms.notifications.index', $this->form));
    $response->assertUnauthorized();
});

it('lists results from the index', function (): void {
    $this->actingAs($this->user);

    $formNotification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    $response = $this->get(route('forms.notifications.index', $this->form));

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'form_id',
                'type',
                'value',
                'enabled',
                'error',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
        ],
        'links' => [
            'first',
            'last',
            'prev',
            'next',
        ],
        'meta' => [
            'current_page',
        ],
    ]);
    $response->assertJsonCount(1, 'data');
});

it('shows single form notification', function (): void {
    $this->actingAs($this->user);

    $formNotification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    $response = $this->get(route('notifications.show', $formNotification));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'form_id',
            'type',
            'value',
            'enabled',
            'error',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
    ]);
});

it('creates a new form notification', function (): void {
    $this->actingAs($this->user);

    $value = fake()->e164PhoneNumber();

    $response = $this->post(route('forms.notifications.store', $this->form), [
        'type' => 'sms',
        'value' => $value,
        'enabled' => true,
    ]);

    $response->assertCreated();
    $response->assertJson([
        'data' => [
            'form_id' => $this->form->id,
            'type' => 'sms',
            'value' => $value,
            'enabled' => true,
            'error' => null,
        ],
    ]);

    $formNotifications = $this->form->notifications()
        ->get();

    $this->assertCount(1, $formNotifications);
    $formNotification = $formNotifications->first();
});

it('enables a new form notification by default', function (): void {
    $this->actingAs($this->user);

    $response = $this->postJson(route('forms.notifications.store', $this->form), [
        'type' => 'email',
        'value' => 'alerts@example.com',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.enabled', true);

    expect($this->form->notifications()->sole()->enabled)->toBeTrue();
});

it('includes timestamps in the form notification resource', function (): void {
    $this->actingAs($this->user);

    $this->travelTo('2026-01-02 03:04:05');
    $formNotification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(route('notifications.show', $formNotification));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'created_at' => '2026-01-02T03:04:05.000000Z',
            'updated_at' => '2026-01-02T03:04:05.000000Z',
            'deleted_at' => null,
        ],
    ]);
});

it('updates a form notification', function (): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'sms', 'enabled' => false]);

    $response = $this->putJson(route('notifications.update', $notification), [
        'type' => 'email',
        'value' => 'alerts@example.com',
        'enabled' => true,
    ]);

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $notification->id,
            'form_id' => $this->form->id,
            'type' => 'email',
            'value' => 'alerts@example.com',
            'enabled' => true,
        ],
    ]);
    expect($notification->fresh())
        ->type->toBe('email')
        ->value->toBe('alerts@example.com')
        ->enabled->toBeTrue();
});

it('rejects a non-boolean enabled value when updating a form notification', function (string $enabled): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => false]);

    $response = $this->putJson(route('notifications.update', $notification), [
        'type' => 'email',
        'value' => 'alerts@example.com',
        'enabled' => $enabled,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('enabled');

    expect($notification->fresh()->enabled)->toBeFalse();
})->with(['banana', 'yes']);

it('does not move a form notification to another form', function (): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);
    $otherForm = Form::factory()->create(['user_id' => $this->user->id]);

    $response = $this->putJson(route('notifications.update', $notification), [
        'form_id' => $otherForm->id,
        'type' => 'email',
        'value' => 'alerts@example.com',
        'enabled' => true,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('form_id');

    expect($notification->fresh()->form_id)->toBe($this->form->id);
});

it('rejects a client-supplied error', function (string $method, string $routeName, bool $existing): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id, 'error' => null]);

    $response = $this->json($method, route($routeName, $existing ? $notification : $this->form), [
        'type' => 'email',
        'value' => 'alerts@example.com',
        'enabled' => true,
        'error' => 'Mailbox full',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('error');

    expect(FormNotification::where('error', 'Mailbox full')->exists())->toBeFalse();
})->with([
    'store' => ['POST', 'forms.notifications.store', false],
    'update' => ['PUT', 'notifications.update', true],
]);

it('rejects read-only fields sent as null on update', function (string $field): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id, 'error' => 'Mailbox full']);

    $response = $this->putJson(route('notifications.update', $notification), [
        'type' => 'email',
        'value' => 'alerts@example.com',
        'enabled' => true,
        $field => null,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($field);

    expect($notification->fresh())
        ->form_id->toBe($this->form->id)
        ->error->toBe('Mailbox full');
})->with(['form_id', 'error']);

it('rejects an error sent as null on create', function (): void {
    $this->actingAs($this->user);

    $response = $this->postJson(route('forms.notifications.store', $this->form), [
        'type' => 'email',
        'value' => 'alerts@example.com',
        'error' => null,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('error');
});

it('validates the value according to the type', function (string $method, string $routeName, bool $existing, string $type, string $value, bool $valid): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    $response = $this->json($method, route($routeName, $existing ? $notification : $this->form), [
        'type' => $type,
        'value' => $value,
        'enabled' => true,
    ]);

    if ($valid) {
        $response->assertSuccessful();
        $response->assertJsonPath('data.value', $value);
    } else {
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('value');
    }
})->with([
    'store' => ['POST', 'forms.notifications.store', false],
    'update' => ['PUT', 'notifications.update', true],
])->with([
    'valid email' => ['email', 'alerts@example.com', true],
    'email that is not an address' => ['email', 'not-an-email', false],
    'phone number given as an email' => ['email', '+14155552671', false],
    'valid E.164 number' => ['sms', '+14155552671', true],
    'number without a plus' => ['sms', '14155552671', false],
    'formatted number' => ['sms', '+1 (415) 555-2671', false],
    'country code starting with 0' => ['sms', '+04155552671', false],
    'more than 15 digits' => ['sms', '+1234567890123456', false],
    'email given as a number' => ['sms', 'alerts@example.com', false],
]);

it('forbids access to recipients of a form the user does not own', function (string $method, string $routeName, bool $onRecipient, array $payload): void {
    $this->actingAs(User::factory()->create());

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'value' => 'owner@example.com']);

    $response = $this->json($method, route($routeName, $onRecipient ? $notification : $this->form), $payload);

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
    expect($this->form->notifications()->sole())
        ->id->toBe($notification->id)
        ->value->toBe('owner@example.com');
})->with([
    'list' => ['GET', 'forms.notifications.index', false, []],
    'show' => ['GET', 'notifications.show', true, []],
    'add' => ['POST', 'forms.notifications.store', false, ['type' => 'email', 'value' => 'intruder@example.com']],
    'update' => ['PUT', 'notifications.update', true, ['type' => 'email', 'value' => 'intruder@example.com', 'enabled' => true]],
    'invalid update' => ['PUT', 'notifications.update', true, ['type' => 'fax']],
]);

it('forbids access to recipients of a deleted form', function (): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);
    $this->form->delete();

    $this->getJson(route('notifications.show', $notification))->assertForbidden();
});

it('soft deletes a form notification', function (): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    $response = $this->deleteJson(route('notifications.destroy', $notification));

    $response->assertNoContent();
    $this->assertSoftDeleted($notification);
    $this->getJson(route('forms.notifications.index', $this->form))->assertJsonCount(0, 'data');
    $this->getJson(route('notifications.show', $notification))->assertNotFound();
});

it('restores a soft deleted form notification', function (): void {
    $this->actingAs($this->user);

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);
    $notification->delete();

    $response = $this->postJson(route('notifications.restore', $notification));

    $response->assertOk();
    $response->assertJson(['data' => ['id' => $notification->id, 'deleted_at' => null]]);
    $this->assertNotSoftDeleted($notification);
});

it('forbids deleting or restoring a recipient of a form the user does not own', function (string $method, string $routeName, bool $trashed): void {
    $this->actingAs(User::factory()->create());

    $notification = FormNotification::factory()->create(['form_id' => $this->form->id]);

    if ($trashed) {
        $notification->delete();
    }

    $response = $this->json($method, route($routeName, $notification));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);

    expect(FormNotification::withTrashed()->find($notification->id)?->trashed())->toBe($trashed);
})->with([
    'delete' => ['DELETE', 'notifications.destroy', false],
    'restore' => ['POST', 'notifications.restore', true],
]);
