<?php

namespace Tests\Feature\Http\Controllers;

use App\Events\FormEntryCreated;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

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
    'invalid trashed value' => [['filter' => ['trashed' => 'all']], 'filter.trashed'],
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
it('soft deletes an entry', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->deleteJson(route('entries.destroy', $entry));

    $response->assertNoContent();
    $this->assertSoftDeleted($entry);
});

it('restores a soft deleted entry', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);
    $entry->delete();

    $response = $this->postJson(route('entries.restore', $entry));

    $response->assertOk();
    $response->assertJson(['data' => ['id' => $entry->id, 'deleted_at' => null]]);
    $this->assertNotSoftDeleted($entry);
});

it('permanently deletes an entry that is already deleted', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);
    $entry->delete();

    $response = $this->deleteJson(route('entries.force-destroy', $entry));

    $response->assertNoContent();
    $this->assertModelMissing($entry);
});

it('refuses to permanently delete an entry that is not deleted', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->deleteJson(route('entries.force-destroy', $entry));

    $response->assertConflict();
    $response->assertJson(['message' => 'Only deleted entries can be permanently deleted.']);
    $this->assertNotSoftDeleted($entry);
});

it('forbids deleting, restoring or permanently deleting an entry on a form the user does not own', function (string $method, string $routeName, bool $trashed): void {
    $this->actingAs(User::factory()->create());

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    if ($trashed) {
        $entry->delete();
    }

    $response = $this->json($method, route($routeName, $entry));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
    expect(FormEntry::withTrashed()->find($entry->id)?->trashed())->toBe($trashed);
})->with([
    'delete' => ['DELETE', 'entries.destroy', false],
    'restore' => ['POST', 'entries.restore', true],
    'force delete' => ['DELETE', 'entries.force-destroy', true],
]);

it('forbids access to entries of a deleted form', function (): void {
    $this->actingAs($this->user);

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);
    $this->form->delete();

    $response = $this->getJson(route('entries.show', $entry));

    $response->assertForbidden();
});

it('lists deleted entries with the trashed filter', function (string $value, array $expectedKeys): void {
    $this->actingAs($this->user);

    $this->travelTo('2026-01-01');
    $entries['kept'] = FormEntry::factory()->create(['form_id' => $this->form->id]);
    $this->travelTo('2026-01-02');
    $entries['deleted'] = FormEntry::factory()->create(['form_id' => $this->form->id]);
    $entries['deleted']->delete();

    $response = $this->getJson(route('forms.entries.index', [$this->form, 'filter' => ['trashed' => $value]]));

    $response->assertOk();

    expect($response->json('data.*.id'))
        ->toBe(array_map(fn (string $key): string => $entries[$key]->id, $expectedKeys));
})->with([
    'with' => ['with', ['kept', 'deleted']],
    'only' => ['only', ['deleted']],
]);

it('applies a bulk triage action to the selected entries', function (string $action, array $before, string $attribute, mixed $expected): void {
    $this->actingAs($this->user);
    $this->travelTo('2026-01-01 00:00:00');

    $selected = FormEntry::factory()->count(2)->create(['form_id' => $this->form->id, ...$before]);
    $untouched = FormEntry::factory()->create(['form_id' => $this->form->id, ...$before]);
    $untouchedValue = $untouched->fresh()->{$attribute};

    $response = $this->postJson(route('forms.entries.bulk', $this->form), [
        'action' => $action,
        'ids' => $selected->modelKeys(),
    ]);

    $response->assertOk();
    $response->assertExactJson(['data' => ['action' => $action, 'affected' => 2]]);
    $selected->each(fn (FormEntry $entry) => expect($entry->fresh()->{$attribute})->toBe($expected));
    expect($untouched->fresh()->{$attribute})->toBe($untouchedValue);
})->with([
    'mark_read' => ['mark_read', ['read_at' => null], 'read_at', 1767225600],
    'mark_unread' => ['mark_unread', ['read_at' => '2026-01-01 00:00:00'], 'read_at', null],
    'star' => ['star', ['starred' => false], 'starred', true],
    'unstar' => ['unstar', ['starred' => true], 'starred', false],
    'mark_spam' => ['mark_spam', ['spam' => null], 'spam', true],
    'mark_not_spam' => ['mark_not_spam', ['spam' => true], 'spam', false],
]);

it('keeps existing read times and counts only changed entries when bulk marking read', function (): void {
    $this->actingAs($this->user);

    $alreadyRead = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => '2025-06-01 00:00:00']);
    $unread = FormEntry::factory()->create(['form_id' => $this->form->id, 'read_at' => null]);

    $response = $this->postJson(route('forms.entries.bulk', $this->form), [
        'action' => 'mark_read',
        'ids' => [$alreadyRead->id, $unread->id],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.affected', 1);
    expect($alreadyRead->fresh()->read_at)->toBe(Carbon::parse('2025-06-01 00:00:00')->timestamp);
    expect($unread->fresh()->read_at)->not->toBeNull();
});

it('bulk deletes, restores and permanently deletes entries', function (): void {
    $this->actingAs($this->user);

    $entries = FormEntry::factory()->count(2)->create(['form_id' => $this->form->id]);
    $ids = $entries->modelKeys();

    $this->postJson(route('forms.entries.bulk', $this->form), ['action' => 'delete', 'ids' => $ids])
        ->assertJsonPath('data.affected', 2);
    $entries->each(fn (FormEntry $entry) => $this->assertSoftDeleted($entry));

    $this->postJson(route('forms.entries.bulk', $this->form), ['action' => 'restore', 'ids' => $ids])
        ->assertJsonPath('data.affected', 2);
    $entries->each(fn (FormEntry $entry) => $this->assertNotSoftDeleted($entry));

    $entries->each(fn (FormEntry $entry) => $entry->delete());

    $this->postJson(route('forms.entries.bulk', $this->form), ['action' => 'force_delete', 'ids' => $ids])
        ->assertJsonPath('data.affected', 2);
    $entries->each(fn (FormEntry $entry) => $this->assertModelMissing($entry));
});

it('rejects a bulk request with entries that are not eligible', function (string $action, FormEntry $entry, string $errorKey): void {
    $this->actingAs($this->user);

    $response = $this->postJson(route('forms.entries.bulk', $this->form), [
        'action' => $action,
        'ids' => [$entry->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($errorKey);
    $this->assertModelExists($entry);
})->with([
    'entry from another form' => ['delete', fn () => FormEntry::factory()->create(), 'ids.0'],
    'deleted entry for a triage action' => ['star', fn () => tap(FormEntry::factory()->create(['form_id' => $this->form->id]))->delete(), 'ids.0'],
    'live entry for force delete' => ['force_delete', fn () => FormEntry::factory()->create(['form_id' => $this->form->id]), 'ids.0'],
    'unknown action' => ['archive', fn () => FormEntry::factory()->create(['form_id' => $this->form->id]), 'action'],
]);

it('rejects a bulk request with too many or no entries', function (int $count): void {
    $this->actingAs($this->user);

    $response = $this->postJson(route('forms.entries.bulk', $this->form), [
        'action' => 'star',
        'ids' => collect()->times($count, fn (): string => (string) Str::ulid())->all(),
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('ids');
})->with([
    'none' => [0],
    'over the limit' => [101],
]);

it('forbids bulk actions on a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());

    $entry = FormEntry::factory()->create(['form_id' => $this->form->id]);

    $response = $this->postJson(route('forms.entries.bulk', $this->form), [
        'action' => 'delete',
        'ids' => [$entry->id],
    ]);

    $response->assertForbidden();
    $this->assertNotSoftDeleted($entry);
});
