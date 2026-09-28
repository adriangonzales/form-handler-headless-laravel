<?php

namespace Tests\Feature\Http\Controllers;

use App\Data\FormSettings;
use App\Events\FormCreated;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('requires authentication to view the form index', function (): void {
    $response = $this->getJson('/api/v1/forms');
    $response->assertUnauthorized();
});

it('lists results from the index', function (): void {
    $user = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $user->id]);

    $user2 = User::factory()->create();
    $form2 = Form::factory()->create(['user_id' => $user2->id]);

    $this->actingAs($user);

    $response = $this->getJson('/api/v1/forms');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'user_id',
                'name',
                'active',
                'schema',
                'settings',
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

it('shows details of a form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson(route('forms.show', $form));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $form->id,
            'user_id' => $form->user_id,
            'name' => $form->name,
            'active' => $form->active,
            'schema' => $form->schema,
            'settings' => $form->settings->toArray(),
        ],
    ]);
});

it('forbids viewing a form owned by another user', function (): void {
    $this->actingAs(User::factory()->create());

    $form = Form::factory()->create();

    $response = $this->getJson(route('forms.show', $form));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
});

it('creates a new form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $name = fake()->name();
    $schema = [
        'email' => [
            'label' => 'Email',
            'rules' => ['required', 'email'],
        ],
    ];
    $settings = ['redirect' => fake()->url()];

    Event::fake();

    $response = $this->postJson(route('forms.store'), [
        'name' => $name,
        'schema' => $schema,
        'settings' => $settings,
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([]);

    $forms = $user->forms()
        ->where('name', $name)
        ->get();

    $this->assertCount(1, $forms);
    $form = $forms->first();
    $this->assertSame($schema, $form->schema);
    $this->assertSame([
        'redirect' => $settings['redirect'],
        'timezone' => null,
        'domains' => [],
    ], $form->settings->toArray());

    Event::assertDispatched(FormCreated::class, function ($event) use ($form) {
        return $event->form->is($form);
    });
});

it('rejects a JSON string schema when creating a form', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'schema' => json_encode(['email' => ['label' => 'Email']]),
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('schema');
});

it('updates a form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);

    $name = fake()->name();
    $active = fake()->boolean();

    $response = $this->putJson(route('forms.update', $form), [
        'name' => $name,
        'active' => $active,
    ]);

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $form->id,
            'user_id' => $user->id,
            'name' => $name,
            'active' => $active,
        ],
    ]);

    $form->refresh();
    $this->assertEquals($user->id, $form->user_id);
    $this->assertEquals($name, $form->name);
    $this->assertEquals($active, $form->active);
});

it('forbids updating a form owned by another user', function (): void {
    $this->actingAs(User::factory()->create());

    $form = Form::factory()->create();
    $originalName = $form->name;

    $response = $this->putJson(route('forms.update', $form), [
        'name' => fake()->name(),
        'active' => true,
    ]);

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
    $this->assertSame($originalName, $form->refresh()->name);
});

it('soft deletes a form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);

    $response = $this->deleteJson(route('forms.destroy', $form));

    $response->assertNoContent();
    $this->assertSoftDeleted($form);
    $this->getJson(route('forms.show', $form))->assertNotFound();
});

it('forbids deleting a form owned by another user', function (): void {
    $this->actingAs(User::factory()->create());

    $form = Form::factory()->create();

    $response = $this->deleteJson(route('forms.destroy', $form));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
    $this->assertNotSoftDeleted($form);
});

it('restores a soft deleted form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);
    $form->delete();

    $response = $this->postJson(route('forms.restore', $form));

    $response->assertOk();
    $response->assertJson(['data' => ['id' => $form->id]]);
    $this->assertNotSoftDeleted($form);
});

it('forbids restoring a form owned by another user', function (): void {
    $this->actingAs(User::factory()->create());

    $form = Form::factory()->create();
    $form->delete();

    $response = $this->postJson(route('forms.restore', $form));

    $response->assertForbidden();
    $this->assertSoftDeleted($form);
});

it('duplicates a form as a new inactive form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->active()->withBasicSchema()->create([
        'user_id' => $user->id,
        'name' => 'Contact Form',
        'settings' => ['redirect' => 'https://example.com/thanks'],
    ]);

    Event::fake();

    $response = $this->postJson(route('forms.duplicate', $form));

    $response->assertCreated();

    $copy = Form::query()->whereKeyNot($form->id)->sole();
    $response->assertJson([
        'data' => [
            'id' => $copy->id,
            'user_id' => $user->id,
            'name' => 'Contact Form (copy)',
            'active' => false,
            'schema' => $form->schema,
            'settings' => $form->settings->toArray(),
        ],
    ]);

    Event::assertDispatched(FormCreated::class, fn (FormCreated $event): bool => $event->form->is($copy));
});

it('keeps a duplicated form name within the length limit', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id, 'name' => str_repeat('a', 400)]);

    $response = $this->postJson(route('forms.duplicate', $form));

    $response->assertCreated();
    expect($response->json('data.name'))
        ->toHaveLength(400)
        ->toEndWith(' (copy)');
});

it('forbids duplicating a form owned by another user', function (): void {
    $this->actingAs(User::factory()->create());

    $form = Form::factory()->create();

    $response = $this->postJson(route('forms.duplicate', $form));

    $response->assertForbidden();
    $this->assertSame(1, Form::query()->count());
});

it('stores and returns every form setting', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $settings = [
        'redirect' => 'https://example.com/thanks',
        'timezone' => 'America/Chicago',
        'domains' => ['example.com', '*.example.org'],
    ];

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'settings' => $settings,
    ]);

    $response->assertCreated();
    $response->assertJson(['data' => ['settings' => $settings]]);

    $form = $user->forms()->sole();
    expect($form->settings)->toBeInstanceOf(FormSettings::class);
    expect($form->settings->toArray())->toBe($settings);
});

it('fills in defaults for omitted form settings', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'settings' => ['timezone' => 'UTC'],
    ]);

    $response->assertCreated();

    expect($response->json('data.settings'))->toBe([
        'redirect' => null,
        'timezone' => 'UTC',
        'domains' => [],
    ]);
});

it('rejects invalid form settings', function (array $settings, string $errorKey): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'settings' => $settings,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'unknown key' => [['captcha' => 'recaptcha'], 'settings'],
    'redirect is not a url' => [['redirect' => 'not a url'], 'settings.redirect'],
    'redirect is too long' => [['redirect' => 'https://example.com/'.str_repeat('a', 2048)], 'settings.redirect'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus_Mons'], 'settings.timezone'],
    'domains is not a list' => [['domains' => ['primary' => 'example.com']], 'settings.domains'],
    'domain is not a hostname' => [['domains' => ['https://example.com/path']], 'settings.domains.0'],
    'domain is not a string' => [['domains' => [123]], 'settings.domains.0'],
]);

it('validates settings when updating a form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);

    $response = $this->putJson(route('forms.update', $form), [
        'name' => $form->name,
        'active' => true,
        'settings' => ['redirect' => 'not a url'],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('settings.redirect');
});
