<?php

namespace Tests\Feature\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Mail\NewFormEntry;
use App\Models\Form;
use App\Models\FormEntry;
use App\Notification\NewFormEntry as NewFormEntryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\FormEntryController
 */
final class FormEntryControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        FormEntry::factory()->count(3)->create();

        $response = $this->get(route('form-entries.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $formEntry = FormEntry::factory()->create();

        $this->get(route('form-entries.show', $formEntry));
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormEntryController::class,
            'store',
            \App\Http\Requests\FormEntryStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $form = Form::factory()->create();
        $spam_score = fake()->randomFloat(
            /** decimal_attributes **/
        );
        $starred = fake()->boolean();
        $data = [];

        Event::fake();
        Notification::fake();
        Mail::fake();

        $response = $this->post(route('form-entries.store'), [
            'form_id' => $form->id,
            'spam_score' => $spam_score,
            'starred' => $starred,
            'data' => $data,
        ]);

        $formEntries = FormEntry::query()
            ->where('form_id', $form->id)
            ->where('spam_score', $spam_score)
            ->where('starred', $starred)
            ->where('data', $data)
            ->get();
        $this->assertCount(1, $formEntries);
        $formEntry = $formEntries->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);

        Event::assertDispatched(FormEntryCreated::class, function ($event) use ($formEntry) {
            return $event->formEntry->is($formEntry);
        });
        Notification::assertSentTo($form->user, NewFormEntryNotification::class);
        Mail::assertSent(NewFormEntry::class, function ($mail) use ($form, $formEntry) {
            return $mail->hasTo($form->user) && $mail->formEntry->is($formEntry);
        });
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\FormEntryController::class,
            'update',
            \App\Http\Requests\FormEntryUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $formEntry = FormEntry::factory()->create();
        $form = Form::factory()->create();
        $spam_score = fake()->randomFloat(
            /** decimal_attributes **/
        );
        $starred = fake()->boolean();

        $response = $this->put(route('form-entries.update', $formEntry), [
            'form_id' => $form->id,
            'spam_score' => $spam_score,
            'starred' => $starred,
        ]);

        $formEntry->refresh();

        $response->assertSessionHas('form_entry.id', $formEntry->id);

        $this->assertEquals($form->id, $formEntry->form_id);
        $this->assertEquals($spam_score, $formEntry->spam_score);
        $this->assertEquals($starred, $formEntry->starred);
    }
}
