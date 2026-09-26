<?php

namespace Tests\Feature\Http\Controllers;

use App\Events\FormCreated;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\FormController
 */
final class FormControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        Form::factory()->count(3)->create();

        $response = $this->get(route('forms.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $form = Form::factory()->create();

        $this->get(route('forms.show', $form));
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormController::class,
            'store',
            \App\Http\Requests\FormStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $schema = [];
        $settings = [];

        Event::fake();

        $response = $this->post(route('forms.store'), [
            'name' => $name,
            'schema' => $schema,
            'settings' => $settings,
        ]);

        $forms = Form::query()
            ->where('name', $name)
            ->where('schema', $schema)
            ->where('settings', $settings)
            ->get();
        $this->assertCount(1, $forms);
        $form = $forms->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);

        Event::assertDispatched(FormCreated::class, function ($event) use ($form) {
            return $event->form->is($form);
        });
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormController::class,
            'update',
            \App\Http\Requests\FormUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $form = Form::factory()->create();
        $user = User::factory()->create();
        $name = fake()->name();
        $active = fake()->boolean();

        $response = $this->put(route('forms.update', $form), [
            'user_id' => $user->id,
            'name' => $name,
            'active' => $active,
        ]);

        $form->refresh();

        $response->assertSessionHas('form.id', $form->id);

        $this->assertEquals($user->id, $form->user_id);
        $this->assertEquals($name, $form->name);
        $this->assertEquals($active, $form->active);
    }
}
