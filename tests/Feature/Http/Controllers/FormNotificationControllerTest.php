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
