<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\FormNotification;

beforeEach(function (): void {
    config([
        'services.postmark.webhook_username' => 'postmark',
        'services.postmark.webhook_password' => 'webhook-secret',
    ]);

    $this->recipient = FormNotification::factory()->create(['type' => 'email', 'error' => null]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postmarkBounce(FormNotification $recipient, array $overrides = []): array
{
    return [
        'RecordType' => 'Bounce',
        'Type' => 'HardBounce',
        'TypeCode' => 1,
        'Email' => $recipient->value,
        'Description' => 'The server was unable to deliver your message (ex: unknown user, mailbox not found).',
        'Metadata' => ['form_notification_id' => $recipient->id],
        ...$overrides,
    ];
}

it('records a hard bounce on the recipient', function (): void {
    $response = $this->withBasicAuth('postmark', 'webhook-secret')
        ->postJson(route('webhooks.postmark.bounces'), postmarkBounce($this->recipient));

    $response->assertNoContent();

    expect($this->recipient->fresh()->error)
        ->toBe('Bounced (HardBounce): The server was unable to deliver your message (ex: unknown user, mailbox not found).');
});

it('records a spam complaint on the recipient', function (): void {
    $this->withBasicAuth('postmark', 'webhook-secret')
        ->postJson(route('webhooks.postmark.bounces'), postmarkBounce($this->recipient, [
            'RecordType' => 'SpamComplaint',
            'Type' => 'SpamComplaint',
            'Description' => 'The subscriber explicitly marked this message as spam.',
        ]))
        ->assertNoContent();

    expect($this->recipient->fresh()->error)->toBe('Marked as spam: The subscriber explicitly marked this message as spam.');
});

it('truncates a long bounce description to the column length', function (): void {
    $this->withBasicAuth('postmark', 'webhook-secret')
        ->postJson(route('webhooks.postmark.bounces'), postmarkBounce($this->recipient, ['Description' => str_repeat('x', 400)]))
        ->assertNoContent();

    expect($this->recipient->fresh()->error)->toHaveLength(255);
});

it('acknowledges but ignores records that are not delivery failures', function (array $overrides): void {
    $this->withBasicAuth('postmark', 'webhook-secret')
        ->postJson(route('webhooks.postmark.bounces'), postmarkBounce($this->recipient, $overrides))
        ->assertNoContent();

    expect($this->recipient->fresh()->error)->toBeNull();
})->with([
    'out-of-office reply' => [['Type' => 'AutoResponder']],
    'delivery event' => [['RecordType' => 'Delivery']],
    'message not sent by this service' => [['Metadata' => []]],
    'unknown recipient' => [['Metadata' => ['form_notification_id' => '01J0000000000000000000000Z']]],
]);

it('rejects webhooks without the configured credentials', function (?array $credentials, ?string $configuredPassword): void {
    config(['services.postmark.webhook_password' => $configuredPassword]);

    $request = $credentials === null ? $this : $this->withBasicAuth(...$credentials);

    $response = $request->postJson(route('webhooks.postmark.bounces'), postmarkBounce($this->recipient));

    $response->assertUnauthorized();

    expect($this->recipient->fresh()->error)->toBeNull();
})->with([
    'no credentials' => [null, 'webhook-secret'],
    'wrong password' => [['postmark', 'guess'], 'webhook-secret'],
    'not configured' => [['postmark', ''], null],
]);
