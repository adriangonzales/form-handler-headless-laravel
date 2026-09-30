<?php

namespace Tests\Feature;

use App\Events\FormEntryCreated;
use App\Jobs\DeliverFormEntryAlert;
use App\Mail\NewFormEntry;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\FormNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->form = Form::factory()->active()->create([
        'user_id' => $this->user->id,
        'name' => 'Contact Us',
        'schema' => [
            'field_1' => ['label' => 'Name', 'rules' => ['required']],
            'field_2' => ['name' => 'message', 'label' => 'Message', 'rules' => ['required']],
        ],
    ]);
});

it('queues an alert for each enabled email recipient when an entry is submitted', function (): void {
    Queue::fake();
    $this->actingAs($this->user);

    $alerted = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
    FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => false]);
    FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'sms', 'enabled' => true]);
    tap(FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]))->delete();
    FormNotification::factory()->create(['type' => 'email', 'enabled' => true]);

    $this->postJson(route('forms.entries.store', $this->form), ['field_1' => 'Ada', 'message' => 'Hello'])->assertCreated();

    Queue::assertPushed(DeliverFormEntryAlert::class, 1);
    Queue::assertPushed(DeliverFormEntryAlert::class, fn (DeliverFormEntryAlert $job): bool => $job->recipient->is($alerted)
        && $job->entry->is($this->form->entries()->sole()));
});

it('does not alert for entries flagged as spam', function (): void {
    Queue::fake();
    FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => true]);

    event(new FormEntryCreated($entry));

    Queue::assertNothingPushed();
});

it('emails the submitted values to the recipient', function (): void {
    Mail::fake();
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true, 'value' => 'alerts@example.com']);
    $entry = FormEntry::factory()->create([
        'form_id' => $this->form->id,
        'input' => ['field_1' => 'Ada <b>Lovelace</b>', 'message' => '[click](https://evil.example)'],
    ]);

    dispatch_sync(new DeliverFormEntryAlert($recipient, $entry));

    Mail::assertSent(NewFormEntry::class, function (NewFormEntry $mail) use ($recipient): bool {
        $html = $mail->render();

        return $mail->hasTo('alerts@example.com')
            && $mail->hasMetadata(NewFormEntry::RECIPIENT_METADATA_KEY, $recipient->id)
            && $mail->hasSubject('New entry: Contact Us')
            && str_contains($html, 'Ada &lt;b&gt;Lovelace&lt;/b&gt;')
            && str_contains($html, '[click](https://evil.example)')
            && ! str_contains($html, 'href="https://evil.example"');
    });
});

it('clears a previous error after a successful delivery', function (): void {
    Mail::fake();
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
    $recipient->forceFill(['error' => 'Mailbox full'])->save();
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    dispatch_sync(new DeliverFormEntryAlert($recipient, $entry));

    expect($recipient->fresh()->error)->toBeNull();
});

it('leaves the error alone while a failed delivery can still be retried', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true, 'error' => null]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    expect(fn () => (new DeliverFormEntryAlert($recipient, $entry))->handle())->toThrow(RuntimeException::class);
    expect($recipient->fresh()->error)->toBeNull();
});

it('records the error on the recipient once delivery has failed for good', function (): void {
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true, 'error' => null]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    (new DeliverFormEntryAlert($recipient, $entry))->failed(new RuntimeException('550 Mailbox unavailable '.str_repeat('x', 300)));

    expect($recipient->fresh()->error)
        ->toStartWith('550 Mailbox unavailable')
        ->toHaveLength(255);
});

it('retries a failed delivery with backoff before giving up', function (): void {
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $job = new DeliverFormEntryAlert($recipient, $entry);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300]);
});

it('skips a recipient that was disabled or deleted after the alert was queued', function (string $change): void {
    Mail::fake();
    $recipient = FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    match ($change) {
        'disabled' => $recipient->update(['enabled' => false]),
        'deleted' => $recipient->delete(),
        'entry deleted' => $entry->delete(),
    };

    dispatch_sync(new DeliverFormEntryAlert($recipient->fresh() ?? FormNotification::withTrashed()->find($recipient->id), $entry));

    Mail::assertNothingSent();
})->with(['disabled', 'deleted', 'entry deleted']);
