<?php

namespace Tests\Feature\Http\Controllers;

use App\Data\FormSettings;
use App\Events\FormCreated;
use App\Models\Form;
use App\Models\FormEntry;
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

it('includes total, unread and spam entry counts in the index', function (): void {
    $user = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $user->id]);
    $emptyForm = Form::factory()->create(['user_id' => $user->id, 'created_at' => now()->addMinute()]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => null, 'spam' => false]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => null, 'spam' => null]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => null, 'spam' => true]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => now(), 'spam' => false]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => now(), 'spam' => true]);
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => null, 'spam' => false])->delete();
    FormEntry::factory()->create(['form_id' => $form->id, 'read_at' => null, 'spam' => true])->delete();
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/forms');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $form->id);
    $response->assertJsonPath('data.0.entries_count', 3);
    $response->assertJsonPath('data.0.unread_entries_count', 2);
    $response->assertJsonPath('data.0.spam_entries_count', 2);
    $response->assertJsonPath('data.1.id', $emptyForm->id);
    $response->assertJsonPath('data.1.entries_count', 0);
    $response->assertJsonPath('data.1.unread_entries_count', 0);
    $response->assertJsonPath('data.1.spam_entries_count', 0);
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
        [
            'id' => '01K6E2E0000000000000000001',
            'order' => 1,
            'label' => 'Email',
            'name' => 'email',
            'rules' => ['email', 'required'],
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
    $response->assertJsonPath('data.active', false);

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
        'message' => null,
        'honeypot_enabled' => false,
        'honeypot_name' => null,
    ], $form->settings->toArray());

    Event::assertDispatched(FormCreated::class, function ($event) use ($form) {
        return $event->form->is($form);
    });
});

it('rejects a JSON string schema when creating a form', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'schema' => json_encode([['id' => '01K6E2E0000000000000000001', 'order' => 1, 'label' => 'Email']]),
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('schema');
});

it('rejects an invalid schema when creating a form', function (mixed $schema, string $errorKey): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'schema' => $schema,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'keyed by field ID' => [['01K6E2E0000000000000000001' => ['order' => 1, 'label' => 'Email']], 'schema'],
    'missing ID' => [[['order' => 1, 'label' => 'Email']], 'schema.0.id'],
    'non-ULID ID' => [[['id' => 'email', 'order' => 1]], 'schema.0.id'],
    'duplicate ID' => [[['id' => '01K6E2E0000000000000000001', 'order' => 1], ['id' => '01K6E2E0000000000000000001', 'order' => 2]], 'schema.1.id'],
    'missing order' => [[['id' => '01K6E2E0000000000000000001', 'label' => 'Email']], 'schema.0.order'],
    'non-integer order' => [[['id' => '01K6E2E0000000000000000001', 'order' => 'first']], 'schema.0.order'],
    'unknown field key' => [[['id' => '01K6E2E0000000000000000001', 'order' => 1, 'type' => 'text']], 'schema.0'],
    'non-string rule' => [[['id' => '01K6E2E0000000000000000001', 'order' => 1, 'rules' => [['required']]]], 'schema.0.rules.0'],
]);

it('returns schema fields sorted by order', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $form = Form::factory()->create([
        'user_id' => $user->id,
        'schema' => [
            ['id' => '01m3x339mch98t20fnxabkq1xs', 'order' => 2, 'label' => 'Email', 'name' => 'email'],
            ['id' => '01K6E2E0000000000000000001', 'order' => 1, 'label' => 'Name', 'name' => 'name'],
        ],
    ]);

    $response = $this->getJson(route('forms.show', $form));

    $response->assertOk();

    expect($response->json('data.schema'))->toBe([
        ['id' => '01K6E2E0000000000000000001', 'order' => 1, 'label' => 'Name', 'name' => 'name'],
        ['id' => '01m3x339mch98t20fnxabkq1xs', 'order' => 2, 'label' => 'Email', 'name' => 'email'],
    ]);
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

it('rejects a non-boolean active value when updating a form', function (string $active): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->inactive()->create(['user_id' => $user->id]);

    $response = $this->putJson(route('forms.update', $form), [
        'name' => $form->name,
        'active' => $active,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('active');

    expect($form->refresh()->active)->toBeFalse();
})->with(['banana', 'yes']);

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
        'message' => 'Thanks, we will be in touch.',
        'honeypot_enabled' => true,
        'honeypot_name' => 'website',
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
        'message' => null,
        'honeypot_enabled' => false,
        'honeypot_name' => null,
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
    'message is too long' => [['message' => str_repeat('a', 2001)], 'settings.message'],
    'honeypot enabled is not a boolean' => [['honeypot_enabled' => 'yes please'], 'settings.honeypot_enabled'],
    'honeypot name is not a field name' => [['honeypot_enabled' => true, 'honeypot_name' => 'contact.website'], 'settings.honeypot_name'],
]);

it('generates a honeypot name when the honeypot is enabled without one', function (?string $honeypotName): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'settings' => ['honeypot_enabled' => true, 'honeypot_name' => $honeypotName],
    ]);

    $response->assertCreated();

    $generatedName = $user->forms()->sole()->settings->honeypot_name;
    expect($generatedName)->toMatch('/^(website|homepage|url|company)_[a-z0-9]{6}$/');
    $response->assertJsonPath('data.settings.honeypot_name', $generatedName);
})->with([
    'omitted' => [null],
    'empty' => [''],
]);

it('does not generate a honeypot name when the honeypot is disabled', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'settings' => ['honeypot_enabled' => false],
    ])->assertCreated();

    expect($user->forms()->sole()->settings->honeypot_name)->toBeNull();
});

it('keeps the stored honeypot name when settings are updated without one', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create([
        'user_id' => $user->id,
        'settings' => ['honeypot_enabled' => true, 'honeypot_name' => 'website_abc123'],
    ]);

    $this->putJson(route('forms.update', $form), [
        'name' => $form->name,
        'active' => true,
        'settings' => ['honeypot_enabled' => true, 'message' => 'Thanks!'],
    ])->assertOk();

    expect($form->fresh()->settings->honeypot_name)->toBe('website_abc123');
});

it('rejects a honeypot name that matches a schema field when creating a form', function (array $schema, string $honeypotName): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'schema' => $schema,
        'settings' => ['honeypot_enabled' => true, 'honeypot_name' => $honeypotName],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['settings.honeypot_name' => 'The honeypot name must not match a schema field.']);
})->with([
    'field ID' => [[['id' => '01K6E2E0000000000000000001', 'order' => 1, 'label' => 'Website']], '01K6E2E0000000000000000001'],
    'field name override' => [[['id' => '01K6E2E0000000000000000001', 'order' => 1, 'name' => 'website', 'label' => 'Website']], 'website'],
]);

it('accepts a honeypot name that only matches an overridden field ID', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.store'), [
        'name' => fake()->name(),
        'schema' => [['id' => '01K6E2E0000000000000000001', 'order' => 1, 'name' => 'url', 'label' => 'Website']],
        'settings' => ['honeypot_enabled' => true, 'honeypot_name' => '01K6E2E0000000000000000001'],
    ]);

    $response->assertCreated();
});

it('checks the honeypot name against the stored schema or settings when updating a form', function (array $stored, array $sent, string $errorKey): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id, ...$stored]);

    $response = $this->putJson(route('forms.update', $form), [
        'name' => $form->name,
        'active' => true,
        ...$sent,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'new settings clash with stored schema' => [
        ['schema' => [['id' => '01K6E2E0000000000000000001', 'order' => 1, 'name' => 'website', 'label' => 'Website']]],
        ['settings' => ['honeypot_enabled' => true, 'honeypot_name' => 'website']],
        'settings.honeypot_name',
    ],
    'new schema clashes with stored settings' => [
        ['settings' => ['honeypot_enabled' => true, 'honeypot_name' => 'website']],
        ['schema' => [['id' => '01K6E2E0000000000000000001', 'order' => 1, 'name' => 'website', 'label' => 'Website']]],
        'schema',
    ],
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

it('includes timestamps in the form resource', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->travelTo('2026-01-02 03:04:05');
    $form = Form::factory()->create(['user_id' => $user->id]);

    $this->travelTo('2026-02-03 04:05:06');
    $form->touch();

    $response = $this->getJson(route('forms.show', $form));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'created_at' => '2026-01-02T03:04:05.000000Z',
            'updated_at' => '2026-02-03T04:05:06.000000Z',
            'deleted_at' => null,
        ],
    ]);
});

it('sorts the form index by created_at', function (?string $sort, array $expectedOrder): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $forms = collect(['2026-01-01', '2026-03-01', '2026-02-01'])
        ->map(function (string $date) use ($user): Form {
            $this->travelTo($date);

            return Form::factory()->create(['user_id' => $user->id]);
        });

    $response = $this->getJson(route('forms.index', array_filter(['sort' => $sort])));

    $response->assertOk();

    expect($response->json('data.*.id'))
        ->toBe(array_map(fn (int $index): string => $forms[$index]->id, $expectedOrder));
})->with([
    'default is oldest first' => [null, [0, 2, 1]],
    'ascending' => ['created_at', [0, 2, 1]],
    'descending' => ['-created_at', [1, 2, 0]],
]);

it('pages the form index by the requested page size', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Form::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->getJson(route('forms.index', ['per_page' => 2]));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('meta.per_page', 2);
    $response->assertJsonPath('meta.last_page', 2);

    expect($response->json('links.next'))->toContain('per_page=2');
});

it('rejects a page size outside 1 to 100', function (mixed $perPage): void {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('forms.index', ['per_page' => $perPage]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('per_page');
})->with([0, 101, 'all', 2.5]);

it('keeps the sort in pagination links', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Form::factory()->count(16)->create(['user_id' => $user->id]);

    $response = $this->getJson(route('forms.index', ['sort' => '-created_at']));

    $response->assertOk();

    expect($response->json('links.next'))->toContain('sort=-created_at');
});

it('rejects an unsupported sort', function (string $sort): void {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('forms.index', ['sort' => $sort]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('sort');
})->with(['active', 'user_id', 'created_at,-name', '--created_at', 'NAME']);

it('sorts the form index by updated_at', function (string $sort, array $expectedOrder): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->travelTo('2026-01-01');
    $forms = Form::factory()->count(3)->create(['user_id' => $user->id])->values();

    $this->travelTo('2026-03-01');
    $forms[0]->touch();
    $this->travelTo('2026-02-01');
    $forms[2]->touch();

    $response = $this->getJson(route('forms.index', ['sort' => $sort]));

    $response->assertOk();

    expect($response->json('data.*.id'))
        ->toBe(array_map(fn (int $index): string => $forms[$index]->id, $expectedOrder));
})->with([
    'ascending' => ['updated_at', [1, 2, 0]],
    'descending' => ['-updated_at', [0, 2, 1]],
]);

it('sorts the form index by name case-insensitively', function (string $sort, array $expectedNames): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (['banana', 'Cherry', 'apple'] as $name) {
        Form::factory()->create(['user_id' => $user->id, 'name' => $name]);
    }

    $response = $this->getJson(route('forms.index', ['sort' => $sort]));

    $response->assertOk();

    expect($response->json('data.*.name'))->toBe($expectedNames);
})->with([
    'ascending' => ['name', ['apple', 'banana', 'Cherry']],
    'descending' => ['-name', ['Cherry', 'banana', 'apple']],
]);

it('filters the form index by active', function (string $value, bool $expectedActive): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $active = Form::factory()->active()->create(['user_id' => $user->id]);
    $inactive = Form::factory()->inactive()->create(['user_id' => $user->id]);

    $response = $this->getJson(route('forms.index', ['filter' => ['active' => $value]]));

    $response->assertOk();

    expect($response->json('data.*.id'))->toBe([($expectedActive ? $active : $inactive)->id]);
})->with([
    'true' => ['true', true],
    '1' => ['1', true],
    'false' => ['false', false],
    '0' => ['0', false],
]);

it('combines the active filter with sorting', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Form::factory()->active()->create(['user_id' => $user->id, 'name' => 'Beta']);
    Form::factory()->active()->create(['user_id' => $user->id, 'name' => 'Alpha']);
    Form::factory()->inactive()->create(['user_id' => $user->id, 'name' => 'Aardvark']);

    $response = $this->getJson(route('forms.index', ['sort' => 'name', 'filter' => ['active' => 'true']]));

    $response->assertOk();

    expect($response->json('data.*.name'))->toBe(['Alpha', 'Beta']);
});

it('keeps the filter in pagination links', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Form::factory()->active()->count(16)->create(['user_id' => $user->id]);

    $response = $this->getJson(route('forms.index', ['filter' => ['active' => 'true']]));

    $response->assertOk();

    expect(urldecode($response->json('links.next')))->toContain('filter[active]=true');
});

it('rejects an invalid filter', function (array $filter, string $errorKey): void {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('forms.index', ['filter' => $filter]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'unknown filter' => [['name' => 'Contact'], 'filter'],
    'invalid active value' => [['active' => 'yes'], 'filter.active'],
]);
