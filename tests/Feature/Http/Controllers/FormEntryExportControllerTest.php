<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Resources\FormEntryExportResource;
use App\Jobs\GenerateFormEntryExport;
use App\Models\Form;
use App\Models\FormEntry;
use App\Models\FormEntryExport;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * @return list<list<string>>
 */
function parseCsv(string $csv): array
{
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

/**
 * Sign a download URL for the export, as the export resource does, regardless of the export's status.
 */
function signedDownloadUrl(FormEntryExport $export): string
{
    return URL::temporarySignedRoute('entry-exports.download', now()->addMinutes(5), $export, absolute: false);
}

/**
 * Request an export (generated immediately on the sync queue) and download its file.
 *
 * @param  array{sort?: string, filter?: array<string, string>}  $parameters
 */
function requestExport(Form $form, array $parameters = []): TestResponse
{
    $export = test()->postJson(route('forms.entries.exports.store', $form), $parameters)
        ->assertAccepted()
        ->assertJsonPath('data.status', FormEntryExport::STATUS_COMPLETED);

    return test()->get($export->json('data.download_url'));
}

beforeEach(function (): void {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->form = Form::factory()->create(['user_id' => $this->user->id]);
});

it('exports entries as CSV with a column per schema field', function (): void {
    $this->actingAs($this->user);
    $form = Form::factory()->withBasicSchema()->create(['user_id' => $this->user->id, 'name' => 'Contact Us']);
    [$nameField, $emailField, $messageField] = array_keys($form->schema);

    $this->travelTo('2026-01-02 03:04:05');
    $entry = FormEntry::factory()->create([
        'form_id' => $form->id,
        'input' => [$nameField => 'Ada Lovelace', $emailField => 'ada@example.com', $messageField => "Hello,\n\"world\""],
        'read_at' => null,
        'starred' => true,
        'spam' => false,
        'spam_score' => 0.25,
        'spam_reason' => null,
        'ip' => '127.0.0.1',
        'referer' => 'https://example.com/contact',
        'user_agent' => 'Mozilla/5.0',
    ]);

    $response = requestExport($form);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertDownload('contact-us-entries-2026-01-02.csv');

    $rows = parseCsv($response->streamedContent());

    expect($rows[0])->toBe(['id', 'created_at', 'Name', 'Email', 'Message', 'read_at', 'starred', 'spam', 'spam_score', 'spam_reason', 'ip', 'referer', 'user_agent', 'deleted_at'])
        ->and($rows[1])->toBe([$entry->id, '2026-01-02T03:04:05Z', 'Ada Lovelace', 'ada@example.com', "Hello,\n\"world\"", '', 'true', 'false', '0.250', '', '127.0.0.1', 'https://example.com/contact', 'Mozilla/5.0', ''])
        ->and($rows)->toHaveCount(2);
});

it('exports values stored under fields the schema no longer has', function (): void {
    $this->actingAs($this->user);
    $form = Form::factory()->create([
        'user_id' => $this->user->id,
        'schema' => [
            'email' => ['label' => 'Email', 'rules' => ['required']],
            'field_2' => ['name' => 'phone_number', 'label' => 'Phone', 'rules' => ['sometimes']],
        ],
    ]);

    $this->travelTo('2026-01-01');
    FormEntry::factory()->create([
        'form_id' => $form->id,
        'input' => ['email' => 'old@example.com', 'phone' => '555-0100', 'company' => 'Acme', '42' => 'numeric key'],
    ]);
    $this->travelTo('2026-01-02');
    FormEntry::factory()->create([
        'form_id' => $form->id,
        'input' => ['email' => 'new@example.com', 'phone_number' => '555-0199'],
    ]);

    $response = requestExport($form);

    $response->assertOk();

    $rows = parseCsv($response->streamedContent());

    expect(array_slice($rows[0], 2, 5))->toBe(['Email', 'Phone', '42', 'company', 'phone'])
        ->and(array_slice($rows[1], 2, 5))->toBe(['old@example.com', '', 'numeric key', 'Acme', '555-0100'])
        ->and(array_slice($rows[2], 2, 5))->toBe(['new@example.com', '555-0199', '', '', '']);
});

it('only adds columns for input keys on the exported entries', function (): void {
    $this->actingAs($this->user);

    FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => true, 'input' => ['kept' => 'yes']]);
    FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => false, 'input' => ['filtered_out' => 'no']]);

    $response = requestExport($this->form, ['filter' => ['starred' => 'true']]);

    $header = parseCsv($response->streamedContent())[0];

    expect($header)->toContain('kept')->not->toContain('filtered_out');
});

it('applies index filters and sort to the export', function (): void {
    $this->actingAs($this->user);

    $this->travelTo('2026-01-01');
    $older = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => true]);
    $this->travelTo('2026-01-02');
    $newer = FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => true]);
    FormEntry::factory()->create(['form_id' => $this->form->id, 'starred' => false]);

    $response = requestExport($this->form, ['sort' => '-created_at', 'filter' => ['starred' => 'true']]);

    $response->assertOk();

    $ids = array_column(array_slice(parseCsv($response->streamedContent()), 1), 0);

    expect($ids)->toBe([$newer->id, $older->id]);
});

it('escapes spreadsheet formulas in exported values', function (): void {
    $this->actingAs($this->user);
    $form = Form::factory()->withBasicSchema()->create(['user_id' => $this->user->id]);
    [$nameField] = array_keys($form->schema);

    FormEntry::factory()->create([
        'form_id' => $form->id,
        'input' => [$nameField => '=HYPERLINK("https://evil.example","click")'],
        'user_agent' => '@SUM(1+1)',
    ]);

    $response = requestExport($form);

    $row = parseCsv($response->streamedContent())[1];

    expect($row[2])->toBe('\'=HYPERLINK("https://evil.example","click")')
        ->and($row[12])->toBe("'@SUM(1+1)");
});

it('forbids exporting entries of a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('forms.entries.exports.store', $this->form));

    $response->assertForbidden();
});

it('queues an export and returns its status', function (): void {
    Queue::fake();
    $this->actingAs($this->user);
    $this->travelTo('2026-01-02 03:04:05');

    $response = $this->postJson(route('forms.entries.exports.store', $this->form), [
        'sort' => '-created_at',
        'filter' => ['starred' => 'true'],
    ]);

    $export = FormEntryExport::sole();

    $response->assertAccepted();
    $response->assertHeader('Location', route('entry-exports.show', $export));
    $response->assertJson([
        'data' => [
            'id' => $export->id,
            'form_id' => $this->form->id,
            'status' => 'pending',
            'parameters' => ['sort' => '-created_at', 'filter' => ['starred' => 'true']],
            'row_count' => null,
            'download_url' => null,
            'expires_at' => '2026-01-03T03:04:05.000000Z',
        ],
    ]);
    Queue::assertPushed(GenerateFormEntryExport::class, fn (GenerateFormEntryExport $job): bool => $job->export->is($export));
});

it('rejects an export with invalid filters', function (): void {
    Queue::fake();
    $this->actingAs($this->user);

    $response = $this->postJson(route('forms.entries.exports.store', $this->form), ['filter' => ['starred' => 'yes']]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('filter.starred');

    expect(FormEntryExport::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('shows a completed export with its row count and download link', function (): void {
    $this->actingAs($this->user);
    FormEntry::factory()->count(3)->create(['form_id' => $this->form->id]);

    $id = $this->postJson(route('forms.entries.exports.store', $this->form))->json('data.id');
    $export = FormEntryExport::findOrFail($id);

    $response = $this->getJson(route('entry-exports.show', $export));

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'status' => 'completed',
            'row_count' => 3,
        ],
    ]);
    expect($response->json('data.download_url'))
        ->toStartWith(route('entry-exports.download', $export).'?expires=')
        ->toContain('&signature=');
    Storage::disk('local')->assertExists($export->path);
});

it('shows no download link until the export is completed', function (string $status): void {
    $this->actingAs($this->user);
    $export = FormEntryExport::factory()->create(['form_id' => $this->form->id, 'status' => $status]);

    $response = $this->getJson(route('entry-exports.show', $export));

    $response->assertOk();
    $response->assertJsonPath('data.status', $status);
    $response->assertJsonPath('data.download_url', null);
})->with([FormEntryExport::STATUS_PENDING, FormEntryExport::STATUS_PROCESSING, FormEntryExport::STATUS_FAILED]);

it('downloads through the signed link without an API token', function (): void {
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id, 'filename' => 'contact-entries.csv']);
    Storage::disk('local')->put($export->path, "id\n");

    $response = $this->get(FormEntryExportResource::make($export)->resolve()['download_url']);

    $response->assertOk();
    $response->assertDownload('contact-entries.csv');
    expect($response->streamedContent())->toBe("id\n");
});

it('accepts the signed link under a different host and scheme, as behind a proxy', function (): void {
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($export->path, 'id');

    $response = $this->get('https://api.example.com'.signedDownloadUrl($export));

    $response->assertOk();
});

it('forbids downloading without a valid signature', function (Closure $url): void {
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    $other = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($export->path, 'id');

    $response = $this->getJson($url($export, $other));

    $response->assertForbidden();
    $response->assertJson(['message' => 'Invalid signature.']);
})->with([
    'no signature' => fn (FormEntryExport $export): string => route('entry-exports.download', $export),
    'altered signature' => fn (FormEntryExport $export): string => signedDownloadUrl($export).'0',
    'another export\'s signature' => fn (FormEntryExport $export, FormEntryExport $other): string => str_replace($other->id, $export->id, signedDownloadUrl($other)),
]);

it('forbids downloading with an API token but no signature', function (): void {
    $this->actingAs($this->user);
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($export->path, 'id');

    $this->getJson(route('entry-exports.download', $export))->assertForbidden();
});

it('forbids downloading once the signed link expires', function (): void {
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($export->path, 'id');
    $url = FormEntryExportResource::make($export)->resolve()['download_url'];

    $this->travel(5)->minutes();
    $this->travel(1)->second();

    $response = $this->getJson($url);

    $response->assertForbidden();
    $response->assertJson(['message' => 'Invalid signature.']);
});

it('refuses to download an export that is not ready', function (): void {
    $export = FormEntryExport::factory()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(signedDownloadUrl($export));

    $response->assertConflict();
    $response->assertJson(['message' => 'This export is not ready.']);
});

it('refuses to download an expired export', function (): void {
    $export = FormEntryExport::factory()->completed()->expired()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($export->path, 'id');

    $response = $this->getJson(signedDownloadUrl($export));

    $response->assertGone();
    $response->assertJson(['message' => 'This export has expired.']);
});

it('forbids viewing an export of a form the user does not own', function (): void {
    $this->actingAs(User::factory()->create());
    $export = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);

    $response = $this->getJson(route('entry-exports.show', $export));

    $response->assertForbidden();
    $response->assertJson(['message' => 'You do not own this form.']);
    $response->assertJsonMissingPath('data.download_url');
});

it('marks the export failed when its form was deleted before it ran', function (): void {
    $export = FormEntryExport::factory()->create(['form_id' => $this->form->id]);
    $this->form->delete();

    dispatch_sync(new GenerateFormEntryExport($export));

    expect($export->fresh())
        ->status->toBe('failed')
        ->error->toBe('The form was deleted.')
        ->path->toBeNull();
});

it('marks the export failed when generation throws', function (): void {
    $export = FormEntryExport::factory()->create(['form_id' => $this->form->id]);

    (new GenerateFormEntryExport($export))->failed(new RuntimeException('Disk full'));

    expect($export->fresh())
        ->status->toBe('failed')
        ->error->toBe('The export could not be generated.');
});

it('prunes expired exports and their files', function (): void {
    $expired = FormEntryExport::factory()->completed()->expired()->create(['form_id' => $this->form->id]);
    $current = FormEntryExport::factory()->completed()->create(['form_id' => $this->form->id]);
    Storage::disk('local')->put($expired->path, 'id');
    Storage::disk('local')->put($current->path, 'id');

    $this->artisan('model:prune', ['--model' => [FormEntryExport::class]])->assertSuccessful();

    $this->assertModelMissing($expired);
    Storage::disk('local')->assertMissing($expired->path);
    $this->assertModelExists($current);
    Storage::disk('local')->assertExists($current->path);
});
