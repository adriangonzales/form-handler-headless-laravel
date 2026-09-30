<?php

namespace Tests\Feature\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->form = Form::factory()->create(['user_id' => $this->user->id]);
});

it('requires authentication to view the form entry index', function (): void {
    $response = $this->get(route('forms.entries.index', $this->form));
    $response->assertUnauthorized();
});

it('lists results from the index', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->get(route('forms.entries.index', $this->form));

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'form_id',
                'input',
                'ip',
                'ip_location_display',
                'referer',
                'user_agent',
                'user_agent_display',
                'spam',
                'spam_score',
                'spam_reason',
                'starred',
                'read_at',
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

it('only lists entries belonging to the requested form', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);
    FormEntry::factory()->create();

    $response = $this->getJson(route('forms.entries.index', $this->form));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $entry->id);
});

it('shows single form entry', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->get(route('entries.show', [$entry]));

    $response->assertOk();
    $response->assertJson(['data' => ['id' => $entry->id]]);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'form_id',
            'input',
            'ip',
            'ip_location_display',
            'referer',
            'user_agent',
            'user_agent_display',
            'spam',
            'spam_score',
            'spam_reason',
            'starred',
            'read_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
    ]);
    $response->assertJsonMissingPath('id');
});

it('creates a new form entry', function (): void {
    $this->actingAs($this->user);
    $this->form->update(['active' => true]);

    $name = fake()->name();

    Event::fake();

    $response = $this->post(route('forms.entries.store', $this->form), [
        'name' => $name,
    ]);

    $response->assertCreated();
    $response->assertJson([
        'data' => [
            'form_id' => $this->form->id,
            'input' => [],
            'ip' => '127.0.0.1',
            'ip_location_display' => null,
            'referer' => null,
            'user_agent' => 'Symfony',
            'user_agent_display' => null,
            'spam' => false,
            'spam_score' => '0.00',
            'spam_reason' => null,
            'starred' => false,
            'read_at' => null,
        ],
    ]);

    $formEntries = $this->form->entries()
        ->get();

    $this->assertCount(1, $formEntries);
    $formEntry = $formEntries->first();

    Event::assertDispatched(FormEntryCreated::class, function ($event) use ($formEntry) {
        return $event->formEntry->is($formEntry);
    });
});

it('stores only validated schema fields as input', function (): void {
    $this->actingAs($this->user);
    $form = Form::factory()->active()->withBasicSchema()->create(['user_id' => $this->user->id]);
    [$nameField, $emailField, $messageField] = array_keys($form->schema);

    $response = $this->postJson(route('forms.entries.store', $form), [
        $nameField => 'Ada Lovelace',
        $emailField => 'ada@example.com',
        $messageField => 'Hello',
        'unexpected' => 'dropped',
    ]);

    $expectedInput = [
        $nameField => 'Ada Lovelace',
        $emailField => 'ada@example.com',
        $messageField => 'Hello',
    ];

    $response->assertCreated();
    $response->assertJsonPath('data.input', $expectedInput);
    $response->assertJsonMissingPath('data.input.unexpected');

    expect($form->entries()->sole()->input)->toBe($expectedInput);
});

it('rejects entries for an inactive form', function (): void {
    $this->actingAs($this->user);
    $form = Form::factory()->inactive()->withBasicSchema()->create(['user_id' => $this->user->id]);

    Event::fake();

    $response = $this->postJson(route('forms.entries.store', $form), []);

    $response->assertForbidden();
    $response->assertJson(['message' => 'This form is not accepting submissions.']);
    $this->assertSame(0, $form->entries()->count());
    Event::assertNotDispatched(FormEntryCreated::class);
});

it('includes timestamps in the form entry resource', function (): void {
    $this->actingAs($this->user);

    $this->travelTo('2026-01-02 03:04:05');
    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(route('entries.show', $entry));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'created_at' => '2026-01-02T03:04:05.000000Z',
            'updated_at' => '2026-01-02T03:04:05.000000Z',
            'deleted_at' => null,
        ],
    ]);
});

it('sorts the entry index by created_at', function (?string $sort, array $expectedOrder): void {
    $this->actingAs($this->user);

    $entries = collect(['2026-01-01', '2026-03-01', '2026-02-01'])
        ->map(function (string $date): FormEntry {
            $this->travelTo($date);

            return FormEntry::factory()->create(['form_id' => $this->form->id]);
        });

    $response = $this->getJson(route('forms.entries.index', [$this->form, ...array_filter(['sort' => $sort])]));

    $response->assertOk();

    expect($response->json('data.*.id'))
        ->toBe(array_map(fn (int $index): string => $entries[$index]->id, $expectedOrder));
})->with([
    'default is oldest first' => [null, [0, 2, 1]],
    'ascending' => ['created_at', [0, 2, 1]],
    'descending' => ['-created_at', [1, 2, 0]],
]);

it('sorts the entry index by spam_score', function (string $sort, array $expectedScores): void {
    $this->actingAs($this->user);

    foreach ([0.5, 0.9, 0.1] as $score) {
        FormEntry::factory()->create(['form_id' => $this->form->id, 'spam_score' => $score]);
    }

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'sort' => $sort]));

    $response->assertOk();

    expect($response->json('data.*.spam_score'))->toBe($expectedScores);
})->with([
    'ascending' => ['spam_score', ['0.10', '0.50', '0.90']],
    'descending' => ['-spam_score', ['0.90', '0.50', '0.10']],
]);

it('filters the entry index by read state', function (string $value, bool $expectRead): void {
    $this->actingAs($this->user);

    $read = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => now()]);
    $unread = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => null]);

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'filter' => ['read' => $value]]));

    $response->assertOk();

    expect($response->json('data.*.id'))->toBe([($expectRead ? $read : $unread)->id]);
})->with([
    'read' => ['true', true],
    'unread' => ['0', false],
]);

it('filters the entry index by starred', function (string $value, bool $expectStarred): void {
    $this->actingAs($this->user);

    $starred = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => true]);
    $unstarred = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => false]);

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'filter' => ['starred' => $value]]));

    $response->assertOk();

    expect($response->json('data.*.id'))->toBe([($expectStarred ? $starred : $unstarred)->id]);
})->with([
    'starred' => ['1', true],
    'not starred' => ['false', false],
]);

it('filters the entry index by spam, treating unchecked entries as not spam', function (string $value, array $expectedKeys): void {
    $this->actingAs($this->user);

    $entries = [
        'spam' => FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => true]),
        'ham' => FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => false]),
        'unchecked' => FormEntry::factory()->create(['form_id' => $this->form->id, 'spam' => null]),
    ];

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'filter' => ['spam' => $value]]));

    $response->assertOk();

    expect($response->json('data.*.id'))
        ->toBe(array_map(fn (string $key): string => $entries[$key]->id, $expectedKeys));
})->with([
    'spam' => ['true', ['spam']],
    'not spam' => ['false', ['ham', 'unchecked']],
]);

it('filters the entry index by an inclusive created date range', function (): void {
    $this->actingAs($this->user);

    $entries = collect([
        '2026-01-31 23:59:59',
        '2026-02-01 00:00:00',
        '2026-02-28 23:59:59',
        '2026-03-01 00:00:00',
    ])->map(function (string $date): FormEntry {
        $this->travelTo($date);

        return FormEntry::factory()->create(['form_id' => $this->form->id]);
    });

    $response = $this->getJson(route('forms.entries.index', [
        $this->form,
        'filter' => ['created_from' => '2026-02-01', 'created_to' => '2026-02-28'],
    ]));

    $response->assertOk();

    expect($response->json('data.*.id'))->toBe([$entries[1]->id, $entries[2]->id]);
});

it('combines entry filters with sorting', function (): void {
    $this->actingAs($this->user);

    $this->travelTo('2026-01-01');
    $older = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => null, 'starred' => true]);
    $this->travelTo('2026-01-02');
    $newer = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => null, 'starred' => true]);
    FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => now(), 'starred' => true]);
    FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => null, 'starred' => false]);

    $response = $this->getJson(route('forms.entries.index', [
        $this->form,
        'sort' => '-created_at',
        'filter' => ['read' => 'false', 'starred' => 'true'],
    ]));

    $response->assertOk();

    expect($response->json('data.*.id'))->toBe([$newer->id, $older->id]);
});

it('keeps the sort and filters in entry pagination links', function (): void {
    $this->actingAs($this->user);

    FormEntry::factory()->count(16)->create(['form_id' => $this->form->id, 'starred' => true]);

    $response = $this->getJson(route('forms.entries.index', [
        $this->form,
        'sort' => '-created_at',
        'filter' => ['starred' => 'true'],
    ]));

    $response->assertOk();

    expect(urldecode($response->json('links.next')))
        ->toContain('sort=-created_at')
        ->toContain('filter[starred]=true');
});

it('rejects an invalid entry sort or filter', function (array $query, string $errorKey): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('forms.entries.index', [$this->form, ...$query]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
})->with([
    'unsupported sort' => [['sort' => 'ip'], 'sort'],
    'combined sort' => [['sort' => 'created_at,-spam_score'], 'sort'],
    'unknown filter' => [['filter' => ['ip' => '127.0.0.1']], 'filter'],
    'invalid boolean' => [['filter' => ['starred' => 'yes']], 'filter.starred'],
    'invalid date' => [['filter' => ['created_from' => '2026-02-01T00:00:00']], 'filter.created_from'],
    'reversed range' => [['filter' => ['created_from' => '2026-02-02', 'created_to' => '2026-02-01']], 'filter.created_to'],
]);

it('forbids listing entries for a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());

    FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'sort' => 'invalid']));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
});

it('forbids showing an entry on a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(route('entries.show', $entry));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
});

it('updates an entry on a form the user owns', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => false]);

    $response = $this->putJson(route('entries.update', $entry), [
        'spam_score' => 0,
        'starred' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.starred', true);
});

it('forbids updating an entry on a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => false]);

    $response = $this->putJson(route('entries.update', $entry), [
        'spam_score' => 0,
        'starred' => true,
    ]);

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);

    expect($entry->fresh()->starred)->toBeFalse();
});

todo('Test that form and entry IDs match');
todo('Test marking as starred');
todo('Test marking as read');
todo('Test marking as unread');
todo('Test soft delete');
todo('Test restore');
todo('Test hard delete');
