<?php

use App\Events\FormEntrySubmitted;
use App\Jobs\DeliverFormEntryAlert;
use App\Listeners\CheckFormEntryForSpam;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\FormNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Classification;
use Laravel\Ai\Prompts\ClassificationPrompt;
use Laravel\Ai\Responses\Data\BooleanAnswer;

beforeEach(function (): void {
    config(['ai.providers.typesafe.key' => 'test-key']);
    $this->form = Form::factory()->active()->create(['name' => 'Contact Us']);
    FormNotification::factory()->create(['form_id' => $this->form->id, 'type' => 'email', 'enabled' => true]);
});

it('checks submissions for spam on the queue', function (): void {
    Queue::fake();

    $this->postJson(route('forms.submissions.store', $this->form))->assertCreated();

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === CheckFormEntryForSpam::class);
    Queue::assertNotPushed(DeliverFormEntryAlert::class);
});

it('flags an entry Jev is confident is spam and does not alert for it', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Classification::fake([['is_spam' => new BooleanAnswer(0.95)]])->preventStrayClassifications();
    $entry = FormEntry::factory()->create([
        'form_id' => $this->form->id,
        'input' => ['message' => 'Cheap SEO backlinks'],
        'spam' => false,
        'spam_score' => 0,
        'spam_reason' => null,
        'spam_checked_at' => null,
    ]);
    $this->freezeSecond();

    event(new FormEntrySubmitted($entry));

    expect($entry->fresh())
        ->spam->toBeTrue()
        ->spam_score->toBe(0.95)
        ->spam_reason->toBe(CheckFormEntryForSpam::REASON)
        ->spam_checked_at->toEqual(now());
    Classification::assertClassified(fn (ClassificationPrompt $prompt): bool => $prompt->asks('is_spam')
        && $prompt->state['form_name'] === 'Contact Us'
        && $prompt->contains('Cheap SEO backlinks'));
    Queue::assertNotPushed(DeliverFormEntryAlert::class);
});

it('keeps an entry below the spam threshold and alerts for it', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Classification::fake([['is_spam' => new BooleanAnswer(0.6)]]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => false, 'spam_reason' => null, 'spam_checked_at' => null]);
    $this->freezeSecond();

    event(new FormEntrySubmitted($entry));

    expect($entry->fresh())
        ->spam->toBeFalse()
        ->spam_score->toBe(0.6)
        ->spam_reason->toBeNull()
        ->spam_checked_at->toEqual(now());
    Queue::assertPushed(DeliverFormEntryAlert::class, 1);
});

it('does not classify an entry already flagged as spam', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Classification::fake();
    $checkedAt = now()->subMinute()->startOfSecond();
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => true, 'spam_reason' => 'Honeypot field was filled in.', 'spam_checked_at' => $checkedAt]);

    event(new FormEntrySubmitted($entry));

    Classification::assertNothingClassified();
    expect($entry->fresh())
        ->spam_reason->toBe('Honeypot field was filled in.')
        ->spam_checked_at->toEqual($checkedAt);
    Queue::assertNotPushed(DeliverFormEntryAlert::class);
});

it('logs the error, records the check and still alerts when the API key is missing', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Log::spy();
    config(['ai.providers.typesafe.key' => null]);
    Classification::fake();
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => false, 'spam_score' => 0.5, 'spam_reason' => null, 'spam_checked_at' => null]);
    $this->freezeSecond();

    event(new FormEntrySubmitted($entry));

    Classification::assertNothingClassified();
    Log::shouldHaveReceived('warning')->once()->with('Could not classify a form entry for spam.', [
        'form_entry_id' => $entry->id,
        'error' => 'Jev is not configured.',
    ]);
    expect($entry->fresh())
        ->spam->toBeFalse()
        ->spam_score->toBe(0.0)
        ->spam_reason->toBeNull()
        ->spam_checked_at->toEqual(now());
    Queue::assertPushed(DeliverFormEntryAlert::class, 1);
});

it('logs a failed classification', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Log::spy();
    Classification::fake(fn () => throw new RuntimeException('Connection timed out'));
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => false]);

    event(new FormEntrySubmitted($entry));

    Log::shouldHaveReceived('warning')->once()->with('Could not classify a form entry for spam.', [
        'form_entry_id' => $entry->id,
        'error' => 'Jev error: Connection timed out',
    ]);
});

it('logs an unexpected error as an error, records the check and still alerts', function (): void {
    Queue::fake([DeliverFormEntryAlert::class]);
    Log::spy();
    Classification::fake([['is_spam' => new BooleanAnswer(0.2)]]);
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => false, 'spam_score' => 0.5, 'spam_reason' => null, 'spam_checked_at' => null]);
    $hasFailed = false;
    FormEntry::updating(function (FormEntry $updatedEntry) use (&$hasFailed): void {
        if (! $hasFailed && $updatedEntry->isDirty('spam_checked_at')) {
            $hasFailed = true;

            throw new RuntimeException('Deadlock found');
        }
    });
    $this->freezeSecond();

    event(new FormEntrySubmitted($entry));

    Log::shouldHaveReceived('error')->once()->with('Could not classify a form entry for spam.', [
        'form_entry_id' => $entry->id,
        'type' => RuntimeException::class,
        'error' => 'Deadlock found',
    ]);
    Log::shouldNotHaveReceived('warning');
    expect($entry->fresh())
        ->spam->toBeFalse()
        ->spam_score->toBe(0.0)
        ->spam_reason->toBe('Unknown error')
        ->spam_checked_at->toEqual(now());
    Queue::assertPushed(DeliverFormEntryAlert::class, 1);
});
