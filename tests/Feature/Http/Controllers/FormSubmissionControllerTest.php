<?php

use App\Events\FormEntryCreated;
use App\Models\Form;
use Illuminate\Support\Facades\Event;

it('accepts a submission without authentication and responds with the redirect and message', function (): void {
    Event::fake();

    $form = Form::factory()->active()->withBasicSchema()->create([
        'settings' => [
            'redirect' => 'https://example.com/thanks',
            'message' => 'Thanks, we will be in touch.',
            'timezone' => 'America/Chicago',
            'domains' => ['example.com'],
        ],
    ]);
    [$nameField, $emailField, $messageField] = array_keys($form->schema);

    $response = $this->withHeaders(['Referer' => 'https://example.com/contact'])
        ->postJson(route('forms.submissions.store', $form), [
            $nameField => 'Ada Lovelace',
            $emailField => 'ada@example.com',
            $messageField => 'Hello',
            'unexpected' => 'dropped',
        ]);

    $response->assertCreated();
    expect($response->json())->toBe([
        'data' => [
            'redirect' => 'https://example.com/thanks',
            'message' => 'Thanks, we will be in touch.',
        ],
    ]);

    $entry = $form->entries()->sole();
    expect($entry->input)->toBe([
        $nameField => 'Ada Lovelace',
        $emailField => 'ada@example.com',
        $messageField => 'Hello',
    ]);
    expect($entry->referer)->toBe('https://example.com/contact');
    expect($entry->ip)->toBe('127.0.0.1');

    Event::assertDispatched(FormEntryCreated::class, fn (FormEntryCreated $event): bool => $event->formEntry->is($entry));
});

it('responds with a null redirect and message when the form has no settings', function (): void {
    $form = Form::factory()->active()->create(['settings' => null]);

    $response = $this->postJson(route('forms.submissions.store', $form));

    $response->assertCreated();
    expect($response->json('data'))->toBe([
        'redirect' => null,
        'message' => null,
    ]);
});

it('validates a submission against the form schema', function (): void {
    $form = Form::factory()->active()->withBasicSchema()->create();
    [, $emailField] = array_keys($form->schema);

    $response = $this->postJson(route('forms.submissions.store', $form), [
        $emailField => 'not an email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(array_keys($form->schema));
    expect($form->entries()->count())->toBe(0);
});

it('rejects submissions to an inactive form', function (): void {
    Event::fake();

    $form = Form::factory()->inactive()->withBasicSchema()->create();

    $response = $this->postJson(route('forms.submissions.store', $form));

    $response->assertForbidden();
    $response->assertJson(['message' => 'This form is not accepting submissions.']);
    expect($form->entries()->count())->toBe(0);
    Event::assertNotDispatched(FormEntryCreated::class);
});

it('returns not found for a deleted form', function (): void {
    $form = Form::factory()->active()->create();
    $form->delete();

    $this->postJson(route('forms.submissions.store', $form))->assertNotFound();
});

it('only accepts submissions referred from an allowed domain', function (?string $referer, bool $allowed): void {
    Event::fake();

    $form = Form::factory()->active()->withBasicSchema()->create([
        'settings' => ['domains' => ['example.com', '*.example.org']],
    ]);

    $response = $this->withHeaders(array_filter(['Referer' => $referer]))
        ->postJson(route('forms.submissions.store', $form));

    if ($allowed) {
        $response->assertUnprocessable();

        return;
    }

    $response->assertForbidden();
    $response->assertJson(['message' => 'Submissions are not accepted from this domain.']);
    Event::assertNotDispatched(FormEntryCreated::class);
})->with([
    'exact domain' => ['https://example.com/contact', true],
    'exact domain in another case' => ['https://EXAMPLE.com/contact', true],
    'wildcard subdomain' => ['https://forms.example.org/', true],
    'nested wildcard subdomain' => ['https://a.b.example.org/', true],
    'bare domain of a wildcard' => ['https://example.org/', false],
    'subdomain of an exact domain' => ['https://www.example.com/', false],
    'lookalike domain' => ['https://evil-example.com/', false],
    'suffix of an allowed domain' => ['https://example.com.evil.net/', false],
    'not a url' => ['example.com', false],
    'missing referer' => [null, false],
]);

it('limits submissions to each form per client', function (): void {
    $form = Form::factory()->active()->create();
    $otherForm = Form::factory()->active()->create();

    foreach (range(1, 60) as $attempt) {
        $this->postJson(route('forms.submissions.store', $form))->assertCreated();
    }

    $this->postJson(route('forms.submissions.store', $form))->assertTooManyRequests();
    $this->postJson(route('forms.submissions.store', $otherForm))->assertCreated();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson(route('forms.submissions.store', $form))
        ->assertCreated();
});

it('limits submissions per client across forms', function (): void {
    $forms = Form::factory()->active()->count(6)->create();

    foreach (range(1, 300) as $attempt) {
        $this->postJson(route('forms.submissions.store', $forms[$attempt % 6]))->assertCreated();
    }

    $this->postJson(route('forms.submissions.store', $forms[0]))->assertTooManyRequests();
});
