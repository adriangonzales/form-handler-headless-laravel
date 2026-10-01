<?php

use App\Events\FormEntrySubmitted;
use App\Listeners\ParseFormEntryUserAgent;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

it('parses the user agent on the queue', function (): void {
    Queue::fake();
    $form = Form::factory()->active()->create();

    $this->postJson(route('forms.submissions.store', $form))->assertCreated();

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === ParseFormEntryUserAgent::class);
    expect($form->entries()->sole()->user_agent_display)->toBeNull();
});

it('stores the parsed platform, browser and browser version of a submission', function (): void {
    $form = Form::factory()->active()->create();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'])
        ->postJson(route('forms.submissions.store', $form))
        ->assertCreated();

    expect($form->entries()->sole()->user_agent_display)->toBe([
        'platform' => 'Macintosh',
        'browser' => 'Chrome',
        'browser_version' => '129.0.0.0',
    ]);
});

it('stores null for the parts of a user agent that cannot be identified', function (): void {
    $form = Form::factory()->active()->create();

    $this->withHeaders(['User-Agent' => 'curl/8.4.0'])
        ->postJson(route('forms.submissions.store', $form))
        ->assertCreated();

    expect($form->entries()->sole()->user_agent_display)->toBe([
        'platform' => null,
        'browser' => 'curl',
        'browser_version' => '8.4.0',
    ]);
});

it('leaves the parsed user agent empty when an entry has no user agent', function (?string $userAgent): void {
    $entry = FormEntry::factory()->create(['user_agent' => $userAgent, 'user_agent_display' => null]);

    event(new FormEntrySubmitted($entry));

    expect($entry->fresh()->user_agent_display)->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
]);

it('returns the parsed user agent as an object in the entry resource', function (): void {
    $user = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $user->id]);
    $entry = FormEntry::factory()->create([
        'form_id' => $form->id,
        'user_agent_display' => ['platform' => 'Windows', 'browser' => 'Firefox', 'browser_version' => '131.0'],
    ]);

    $this->actingAs($user)
        ->getJson(route('entries.show', $entry))
        ->assertOk()
        ->assertJsonPath('data.user_agent_display', ['platform' => 'Windows', 'browser' => 'Firefox', 'browser_version' => '131.0']);
});
