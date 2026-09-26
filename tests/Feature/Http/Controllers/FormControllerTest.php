<?php

namespace Tests\Feature\Http\Controllers;

use App\Events\FormCreated;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('requires authentication to view the form index', function (): void {
    $response = $this->get('/api/v1/forms');
    $response->assertUnauthorized();
});

it('lists results from the index', function (): void {
    $user = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $user->id]);

    $user2 = User::factory()->create();
    $form2 = Form::factory()->create(['user_id' => $user2->id]);

    $this->actingAs($user);

    $response = $this->get('/api/v1/forms');

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
            ]
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

    $form = $user->forms()->getModel()->factory()->create();

    $response = $this->get(route('forms.show', $form));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'id' => $form->id,
            'user_id' => $form->user_id,
            'name' => $form->name,
            'active' => $form->active,
            'schema' => $form->schema,
            'settings' => $form->settings,
        ],
    ]);
});

it('creates a new form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $name = fake()->name();
    $schema = [];
    $settings = [];

    Event::fake();

    $response = $this->post(route('forms.store'), [
        'name' => $name,
        'schema' => json_encode($schema),
        'settings' => json_encode($settings),
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([]);

    $forms = $user->forms()
        ->where('name', $name)
        ->get();

    $this->assertCount(1, $forms);
    $form = $forms->first();

    Event::assertDispatched(FormCreated::class, function ($event) use ($form) {
        return $event->form->is($form);
    });
});

it('updates a form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $form = Form::factory()->create(['user_id' => $user->id]);

    $name = fake()->name();
    $active = fake()->boolean();

    $response = $this->put(route('forms.update', $form), [
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
        ]
    ]);

    $form->refresh();
    $this->assertEquals($user->id, $form->user_id);
    $this->assertEquals($name, $form->name);
    $this->assertEquals($active, $form->active);
});
