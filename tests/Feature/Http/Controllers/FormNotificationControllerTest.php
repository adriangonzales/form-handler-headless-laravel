<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Form;
use App\Models\FormNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\FormNotificationController
 */
final class FormNotificationControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        FormNotification::factory()->count(3)->create();

        $response = $this->get(route('form-notifications.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $formNotification = FormNotification::factory()->create();

        $this->get(route('form-notifications.show', $formNotification));
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormNotificationController::class,
            'store',
            \App\Http\Requests\FormNotificationStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $type = fake()->randomElement(/** enum_attributes **/);
        $value = fake()->word();

        $response = $this->post(route('form-notifications.store'), [
            'type' => $type,
            'value' => $value,
        ]);

        $formNotifications = Form::query()
            ->where('type', $type)
            ->where('value', $value)
            ->get();
        $this->assertCount(1, $formNotifications);
        $formNotifications->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormNotificationController::class,
            'update',
            \App\Http\Requests\FormNotificationUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $formNotification = FormNotification::factory()->create();
        $form = Form::factory()->create();
        $type = fake()->randomElement(/** enum_attributes **/);
        $value = fake()->word();
        $enabled = fake()->boolean();

        $response = $this->put(route('form-notifications.update', $formNotification), [
            'form_id' => $form->id,
            'type' => $type,
            'value' => $value,
            'enabled' => $enabled,
        ]);

        $formNotification->refresh();

        $response->assertSessionHas('formNotification.id', $formNotification->id);

        $this->assertEquals($form->id, $formNotification->form_id);
        $this->assertEquals($type, $formNotification->type);
        $this->assertEquals($value, $formNotification->value);
        $this->assertEquals($enabled, $formNotification->enabled);
    }
}
